# Информация
###Телеграм бот продуктового магазтина с доставкой
####Бот отправляет заявку в группу администраторов. Администоры принимают/отклоняют заказ. Соответствующее сообщение отправляется клиенту.
Принятая заявка попадает в группу исполнителей. Каждый из исполнителей, подготавливет заказ к обработке или доставке, нажимая соответующую кнопку. Статус заказа можно просмотреть в группе исполнителей и администраторов.



# Screenshots

![](https://pandao.github.io/editor.md/examples/images/4.jpg)
> Follow your heart.

# Развертывание проекта
1. Точка входа - index.php
2. Класс БД и Телеграм - classes.php
3. Токен бота и Id групп задаются в конструкторе класса telega
4. Сервер БД - mysql. Необходимые таблицы в файле dump.sql
