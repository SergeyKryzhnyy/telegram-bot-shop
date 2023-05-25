# Информация
Телеграм бот продуктового магазтина с доставкой

Бот отправляет заявку в группу администраторов. Администоры принимают/отклоняют заказ. Соответствующее сообщение отправляется клиенту.
Принятая заявка попадает в группу исполнителей. Каждый из исполнителей, подготавливет заказ к обработке или доставке, нажимая соответующую кнопку. Статус заказа можно просмотреть в группе исполнителей и администраторов.



# Screenshots

[![](https://github.com/SergeyKryzhnyy/telegram-bot-shop/blob/main/admin_group.jpg?raw=true)](https://github.com/SergeyKryzhnyy/telegram-bot-shop/blob/main/admin_group.jpg?raw=true)
> Admin Panel.

[![](https://github.com/SergeyKryzhnyy/telegram-bot-shop/blob/main/users.jpg?raw=true)](https://github.com/SergeyKryzhnyy/telegram-bot-shop/blob/main/users.jpg?raw=true)
> Users Panel.

# Развертывание проекта
1. Точка входа - index.php
2. Класс БД и Телеграм - classes.php
3. Токен бота и Id групп задаются в конструкторе класса telega
4. Сервер БД - mysql. Необходимые таблицы в файле dump.sql
5. Для работы бота нужен веб-хук и SSL.
