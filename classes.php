<?php
namespace options;

class telega
{
    public $mainUrl;
    private $token;
    private $adminId;
    public $method;
    public $userId;
    public $text;
    public $userName;
    public $messageId;
    public $data;
    public $replay;
    public $replyMessageId; // сообщение на которое ответили
    public $replyUserId;
    public $orderData;
    public $isOrder = false;
    public $setOrder;
    public $messageOrderId;
    public $groupId = '';
    public $groupStat;
    public $messageGroupId;
    public $orderText;

    public function __construct($data)
    {
        $arr = (array)$data;
        $this->data = $data;
        $this->writeLogFile($this->data, true);
        $this->adminId = '';
        $this->token = '';
        $this->mainUrl = 'https://api.telegram.org/bot' . $this->token;

        $this->userId = $arr['message']['from']['id'];
        if (isset($arr['callback_query']['message']['message_id']))  $this->messageId = $arr['callback_query']['message']['message_id'];
        if (isset($arr['message']['message_id']) && $arr['message']['message_id'] != '')
        {
            $this->messageId = $arr['message']['message_id'];
            $this->userId = $arr['message']['from']['id'];
            $this->userName = $arr['message']['from']['username'];
            $this->text = $arr['message']['text'];
            $this->isOrder = false;
        }  

        if ($arr['message']['reply_to_message'])/// ответ на сообщение
        {
            $answeredMessege = $arr['message']['reply_to_message']['message_id']; /// здесь проеб - ответ приходит мне а не клиенту
            $this->userId = $arr['message']['reply_to_message']['chat']['id'];
            $this->writeLogFile($this->data, true);
            $userId = $arr['message']['from']['id'];
            $this->deleteMessage($this->adminId, $answeredMessege);
            //$this->editMessage($this->adminId, $answeredMessege);
            $this->messageOrderId = $answeredMessege;
            $this->sendMessage($this->text, $userId, true);/// ответ без заказа
            die();
        }
        
        if (isset($arr['callback_query']['data']))///принять/отклонть заказ
        {
            $request = explode('=', $arr['callback_query']['data']);
            $this->setOrder = $request[0];
            $this->userId = $request[1];
            $this->text = explode('Заказ: ', $arr['callback_query']['message']['text'])[1];
            $this->messageOrderId = $request[2];
            $this->isOrder = true;
            $this->messageId = $arr['callback_query']['message']['message_id'];
        }

        if ($arr['callback_query']['message']['chat']['id'] == $this->adminId && explode(':', $arr['callback_query']['data'])[0] == 'check')
        {
            $stat = explode(':', $arr['callback_query']['data']);
            $orderId = $stat[1];
            $q = "select message_id, text, status, id_user from orders where id='$orderId'";
            $result = Connection::getResult($q);
            $messageId = $result[0]['messsage_id'];
            $userId = $result[0]['id_user'];
            $status = $result[0]['status'];
            $text = $result[0]['text'];
            if ($status == 1) $messageStat = 'В обработку не принят';
            if ($status == 2) $messageStat = 'В обработке';
            if ($status == 3) $messageStat = 'Доставляется клиенту';
            if ($status == 4) $messageStat = 'Доставлен';
            $q = "select name from users where telegram_id='$userId'";
            $userName = Connection::getResult($q)[0]['name'];
            $str = "Заказ № ". $orderId ." от " . $userName . PHP_EOL. $text . PHP_EOL . "Статус: " . $messageStat;
            $this->sendMessage($str, $this->adminId, false);
        }

        if ($arr['callback_query']['message']['chat']['id'] == $this->groupId)
        {
            $stat = explode(':', $arr['callback_query']['data']);
            $orderId = $stat[1];
            if ($stat[0] == 'InJob')
            {
                $q = "update orders set status=2 where id='$orderId'";
                Connection::writeData($q);
                $q = "select id_user, text, group_message_id from orders where id='$orderId'";
                $text = Connection::getResult($q)[0]['text'];
                $userId = Connection::getResult($q)[0]['id_user'];
                $messageGroupId = Connection::getResult($q)[0]['group_message_id'];

                $q = "select name from users where telegram_id='$userId'";
                $userName = Connection::getResult($q)[0]['name'];
                $this->editMessageInGroup($orderId, 2, $userName,$text, $messageGroupId);
                die();
            }
            if ($stat[0] == 'InDelivery')
            {
                $q = "update orders set status=3 where id='$orderId'";
                Connection::writeData($q);
                $q = "select id_user, text, group_message_id from orders where id='$orderId'";
                $text = Connection::getResult($q)[0]['text'];
                $userId = Connection::getResult($q)[0]['id_user'];
                $messageGroupId = Connection::getResult($q)[0]['group_message_id'];

                $q = "select name from users where telegram_id='$userId'";
                $userName = Connection::getResult($q)[0]['name'];
                $this->editMessageInGroup($orderId, 3, $userName,$text, $messageGroupId);
                die();
            }
            if ($stat[0] == 'DeliveryDone')
            {
                $q = "update orders set status=4 where id='$orderId'";
                Connection::writeData($q);
                $q = "select id_user, text, group_message_id from orders where id='$orderId'";
                $text = Connection::getResult($q)[0]['text'];
                $userId = Connection::getResult($q)[0]['id_user'];
                $messageGroupId = Connection::getResult($q)[0]['group_message_id'];

                $q = "select name from users where telegram_id='$userId'";
                $userName = Connection::getResult($q)[0]['name'];
                $this->editMessageInGroup($orderId, 4, $userName,$text, $messageGroupId);
                die();
            }
            // статусы 1-на рассмотрении группы
            // 2-принято в группе
            // 3.

            // $this->groupStat = explode(':', $arr['callback_query']['data'])[1];//id заказа
            // $this->messageGroupId = $arr['callback_query']['message']['message_id'];
            // $q = "select id_user from ordres where id='$this->groupStat'";
            // $this->userName = Connection::getResult($q)[0]['id_user'];
            
        } 
    }

   public function sayHello()
   {
        $message = sprintf("Добро пожаловать %s! Для того, чтобы сделать заказ напишите боту короткое сообщение, в котором будут: название, количество/вес, вид оплаты, адрес доставки", $this->userName);
        $this->sendMessage($message, $this->userId, false);
   }

    public function readMessage()
    {
        if ($this->text == "/start") $this->sayHello(); 

        if ($this->text !== "/start")
        {
            $arr = (array)$this->data;
            //if (isset($this->userId) && !$this->isOrder) ///&& !$this->isAdmin())
            if (($arr['message']['from']['id']) && !$this->isOrder) ///&& !$this->isAdmin())
            {
                if (isset($arr['message']['from']['username'])) $userName = $arr['message']['from']['username'];
                if (isset($arr['message']['from']['first_name'])) $userName = $arr['message']['from']['first_name'];
                if (!isset($arr['message']['from']['username']) && !isset($arr['message']['from']['first_name']))
                {
                    $userName = $arr['message']['from']['last_name'];
                }
                $userId = $arr['message']['from']['id'];
                $this->addNewUser($userId, $userName);
                if (isset($arr['message']['message_id']))  $this->messageId = $arr['message']['message_id'] + 1;
                $this->sendOrderToAdmin();
            }

            if ($this->isOrder)///обработка заказа
            {
                if ($this->setOrder == 'takeOrder')
                {            
                    //$created_at = date(DATE_RFC822);
                    $q = "insert into orders (id_user, message_id,  text, status, group_message_id) values('$this->userId', '$this->messageOrderId', '$this->text', 1, '$this->messageGroupId')";
                    $text = $this->text;
                    $orderId = Connection::writeData($q);
                    $this->sendMessage('ваш заказ принят!' .PHP_EOL. 'id: ' . $orderId, $this->userId);
                    $this->text = sprintf("Заказ №%ы передан исполнителю, Статус - в обработке", $orderId);
                    $this->editMessage1($this->adminId, $this->messageId, $orderId);
                    $q = "select name from users where telegram_id='$this->userId'";
                    $userName = Connection::getResult($q)[0]['name'];
                    $this->sendMessageInGroup($orderId, $text, $userName);///взять отсюда id сообщения группы

                    // $this->sendMessage('принято! id:' . $orderId .PHP_EOL. $this->text, $this->adminId, true);
                    // $this->deleteMessage($this->userId, $this->messageId);
                    // $this->sendMessageInGroup($orderId);
                }
                if ($this->setOrder == 'aboardOrder' )
                {
                    $this->sendMessage('ваш заказ отклонен!', $this->userId);
                    $this->deleteMessage($this->userId, $this->messageId);
                    $this->sendMessage('отклонено',  $this->adminId, false);
                } 
            }
        }

    }
    public function editMessage($chat_id, $messageId)
    {
            $params = [
                'chat_id' => $chat_id,
                'message_id' => $messageId,
                'text' => $this->text,
                'parse_mode' => 'html',

            ];
        $str = $this->mainUrl . '/editMessageText?' . http_build_query($params);
        $ch = curl_init($str);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        $this->writeLogFile(json_decode($result), true);
    }



    public function editMessage1($chat_id, $messageId, $orderId)
    {
            $params = [
                'chat_id' => $chat_id,
                'message_id' => $messageId,
                'text' => ('Заказ №' . $orderId .  ' - Передан в обработку исполнителям '),
                'parse_mode' => 'html',
                'reply_markup' => json_encode(array(
                    'inline_keyboard' => array(
                        array(
                            array(
                                'text' => 'Статус заказа',
                                'callback_data' => 'check:' . $orderId,
                            ),
            
                        )
                    ),
                ))

            ];
        $str = $this->mainUrl . '/editMessageText?' . http_build_query($params);
        $ch = curl_init($str);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        //$this->writeLogFile(json_decode($result), true);
    }



    public  function editMessageInGroup($orderId,  $status, $userName, $text, $messageId)
    {
        if ($status == 2)
        {
            $params = [
                'chat_id' => $this->groupId,
                'message_id' => $messageId,
                'text' => sprintf("заказ от %s " .PHP_EOL. "Заказ № %s: %s" .PHP_EOL. "Статус: %s" , $userName, $orderId, $text, 'Заказ в обработке'),
                'parse_mode' => 'html',
                'reply_markup' => json_encode(array(
                    'inline_keyboard' => array(
                        array(
                            array(
                                'text' => 'Доставка',
                                'callback_data' => 'InDelivery:' . $orderId,
                            ),
                            array(
                                'text' => 'Доставлено',
                                'callback_data' => 'DeliveryDone:' . $orderId,
                            ),
                        ),
                        
                    ),
                )),
            ];
        }
        if ($status == 3)
        {
            $params = [
                'chat_id' => $this->groupId,
                'message_id' => $messageId,
                'text' => sprintf("заказ от %s " .PHP_EOL. "Заказ № %s: %s" .PHP_EOL. "Статус: %s" , $userName, $orderId, $text, 'В доставке'),
                'parse_mode' => 'html',
                'reply_markup' => json_encode(array(
                    'inline_keyboard' => array(
                        array(
                            array(
                                'text' => 'Доставлено',
                                'callback_data' => 'DeliveryDone:' . $orderId,
                            ),
                        ),
                        
                    ),
                )),
            ];
        }
        if ($status == 4)
        {
            $params = [
                'chat_id' => $this->groupId,
                'message_id' => $messageId,
                'text' => sprintf("заказ от %s " .PHP_EOL. "Заказ № %s: %s" .PHP_EOL. "Статус: %s" , $userName, $orderId, $text, 'Доставлено'),
                'parse_mode' => 'html',
            ];
        }
        $str = $this->mainUrl . '/editMessageText?' . http_build_query($params);
        $ch = curl_init($str);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        $this->writeLogFile(json_decode($result), true);
    }







    public  function sendMessageInGroup($orderId, $text, $userName)///здесь записать id сообщения группы в orders
    {
        $textOrder = sprintf("заказ от %s " .PHP_EOL. "Заказ № %s: %s" .PHP_EOL. "Статус: %s" , $userName, $orderId, $text, 'Передано в обработку');////теряется username
        $params = [
            'chat_id' => $this->groupId,
            'text' => $textOrder,
            'parse_mode' => 'html',
            'reply_markup' => json_encode(array(
                'inline_keyboard' => array(
                    array(
                        array(
                            'text' => 'В обработке',
                            'callback_data' => 'InJob:' . $orderId,
                        ),
                        array(
                            'text' => 'Доставка',
                            'callback_data' => 'InDelivery:' . $orderId,
                        ),
                        array(
                            'text' => 'Доставлено',
                            'callback_data' => 'InDelivery:' . $orderId,
                        ),
                    ),
                    
                ),
            )),
        ];
        $ch = curl_init($this->mainUrl . '/sendMessage?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        $data = (array)json_decode($result);
        $messageGroupId = $data['result']->message_id;
         $q = "update orders set group_message_id='$messageGroupId' where id='$orderId'";
         Connection::writeData($q);
         
    }

    public  function sendMessageInGroup2($orderId)
    {
        $textOrder = sprintf("заказ от %s " .PHP_EOL. "Заказ: %s" , $this->userName, $this->text);////записать текст заказа в базу
        $params = [
            'chat_id' => $this->groupId,
            'text' => $textOrder,
            'parse_mode' => 'html',
            'reply_markup' => json_encode(array(
                'inline_keyboard' => array(
                    array(
                        array(
                            'text' => 'Передано в доставку',
                            'callback_data' => 'GetDeliv:' . $orderId,
                        ),
        
                    )
                ),
            )),
        ];

        $ch = curl_init($this->mainUrl . '/sendMessage?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
    }
    public function deleteMessage($userId, $messageId)
    {

        $params = [
                'chat_id' => $userId,
                'message_id' => $messageId,
        ];



        $ch = curl_init($this->mainUrl . '/deleteMessage?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);

        $result = curl_exec($ch);
        curl_close($ch);
    }




    public function addNewUser($userId, $userName)
    {
        $q = "select id from users where telegram_id='$userId' or name='$userName'";
        $result = Connection::getResult($q);
        if (!$result)
        {
            $q = "insert into users (name, telegram_id, status) values('$userName', '$userId', 0)";
            Connection::writeData($q);
        }
    }


  



    public function isAdmin()
    {
        if ($this->userId == $this->adminId) return true;
        if ($this->userId != $this->adminId) return false;
    }    



    public  function sendOrderToAdmin()
    {
        $textOrder = sprintf("Новый заказ от %s " .PHP_EOL. "Заказ: %s" , $this->userName, $this->text);////записать текст заказа в базу
        $params = [
            'chat_id' => $this->adminId,
            'text' => $textOrder,
            'parse_mode' => 'html',
            'reply_markup' => json_encode(array(
                'inline_keyboard' => array(
                    array(
                        array(
                            'text' => 'Принять',
                            'callback_data' => 'takeOrder=' . $this->userId. '=' . $this->messageId,
                        ),
                        array(
                            'text' => 'Отклонить',
                            'callback_data' => 'aboardOrder=' . $this->userId. '='. $this->messageId,
                        ),
                    )
                ),
            )),
        ];

        $ch = curl_init($this->mainUrl . '/sendMessage?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        //$this->writeLogFile(json_decode($result, true));
    }

    
    public  function sendKeyboardAdmin()
    {
        $params = [
            'chat_id' => $this->adminId,
            'text' => 'safsdf',
            'parse_mode' => 'html',
            'reply_markup' => json_encode(array(
                'keyboard' => array(
                    array(
                        array(
                            'text' => 'Показать неактивные заказы',
                            'url' => 'YOUR BUTTON URL',
                        ),
                        array(
                            'text' => 'Показать заказы в работе',
                            'url' => 'YOUR BUTTON URL',
                        ),
                    )
                ),
                'one_time_keyboard' => TRUE,
                'resize_keyboard' => TRUE,
            )),
        ];

        $ch = curl_init($this->mainUrl . '/sendMessage?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
    }



    public function sendMessage($string, $user_id, $stat=false)
    {
        
        if ($stat==false)
        {
            $params = [
                'chat_id' => $user_id,
                'text' => $string,
                'parse_mode' => 'html'
            ];
        }
        else
        {
            $params = [
                'chat_id' => $user_id,
                'text' => $string,
                'parse_mode' => 'html',
                'reply_to_message_id' => $this->messageOrderId
            ];
        }


        $ch = curl_init($this->mainUrl . '/sendMessage?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_exec($ch);
        curl_close($ch);
    }



    public function writeLogFile($string, $clear=false)
    {
        $logFileName = __DIR__ . "/messageAnswer.txt";
        $now = date("Y-m-d H:i:s");
        if ($clear == false)
        {
            file_put_contents($logFileName, $now . ' '. print_r($string, true) . "\r\n, FILE_FPPEND");
        }
        else
        {
            file_put_contents($logFileName, '');
                file_put_contents($logFileName, $now . ' '. print_r($string, true) . "\r\n, FILE_FPPEND");
        }
    }



    public function setHook()
    {
        $q = [
            'url' => "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"
        ];
        $ch = curl_init($this->mainUrl . '/setWebhook?' . http_build_query($q));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        echo "<pre>";
        var_dump($result);
        die();
        return $result;
    }

    public function delHook()
    {
        $q = [
            'url' => "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"
        ];
        $ch = curl_init($this->mainUrl . '/deleteWebhook?' . http_build_query($q));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        echo "<pre>";
        var_dump($result);
        die();
        return $result;
    }



}


class Connection
{
    public $host;
    public $userName;
    public $password;
    public $dbName;
    public $lastId;

    public function __construct()
    {
        // $this->host = "";
        // $this->userName = "u147281_serega";
        // $this->password = "";
        // $this->dbName = "";
    }

    static function getResult($q)
    {
        $mysqli = mysqli_connect("localhost", "siman1oy_db", "11177zxx11177ZXX", "siman1oy_db");
        $result = mysqli_query($mysqli, $q);
        $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        if ($mysqli->errno) 
        {
            die('Select Error (' . $mysqli->errno . ') ' . $mysqli->error);
            return $result;
        }
        return $rows;
    }

    static function writeData($q)
    {
        $mysqli = mysqli_connect("localhost", "siman1oy_db", "11177zxx11177ZXX", "siman1oy_db");
        mysqli_query($mysqli, $q);
        return $mysqli->insert_id;
    }
}
