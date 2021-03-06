Микросервис баланса пользователей.
==============================
Приложение хранит в себе идентификаторы пользователей и их баланс. Взаимодействие с ним
осуществляется исключительно с помощью брокера очередей.

По требованию внешней системы (общение между микросервисами через очередь сообщений),
микросервис может выполнить одну из следующих операций со счетом пользователя:

* Списание
* Зачисление
* (будет плюсом, но не обязательно) Блокирование с последующим списанием или
разблокированием. Заблокированные средства недоступны для использования. Блокировка
означает что некая операция находится на авторизации и ждет какого-то внешнего
подтверждения, ее можно впоследствии подтвердить или отклонить
* Перевод от пользователя к пользователю

После проведения любой из этих операций генерируется событие.
Основные требования к воркерам:
* Код воркеров должен безопасно выполняться параллельно в разных процессах;
* Воркеры могут запускаться одновременно в любом числе экземпляров и
выполняться произвольное время;
* Все операции должны обрабатываться корректно, без двойных списаний.

Требования к окружению:
Язык программирования: PHP >= 7, стандарт кодирования - PSR-2.
Можно использовать: любые фреймворки, реляционные БД для хранения баланса, брокеры
очередей, key-value хранилища.

Реализация
---------------------------------
Используемые технологии: PHP 7.1, Mysql 5.7, Gearman.

Для запуска и проверки используются 2 команды:

./balance worker --count=10 --timeout=100 -vvv

./balance client --file=operations.json --timeout=100 -vvv

Все параметры можно опустить и задать по умолчанию из файла config.json.

При запуске команды ./balance worker создается пулл воркеров, которые вычитывают из 3 очередей:
 * deposit
 * withdraw
 * transfer

Параметр count отвечает за количество воркеров, timeout за время жизни воркера без новых задач.
Для предотвращения "race condition" используются блокировки с транзакциями mysql.
Для конкурентного логирования несколькими воркерами используется семафор (QXS\WorkerPool\Semaphore).
После выполнения каждой задачи воркер отправляет сообщение в лог и результат в очередь balance_result.

При запуске команды ./balance client создаются задачи из файла operations.json.
После этого клиент слушает события из balance_result и сообщает об этом в лог.

Запуск
---------------------------------
docker-compose up

Поднимает 4 контейнера:
 * phpworker
 * phpclient
 * gearman
 * mysql

Просмотреть очередь gearman с хоста можно с помощью gearadmin:

gearadmin --host=localhost --port=3000 --status

Посмотреть результат изменения баланса:

подключившись к mysql к БД balance по порту 4000.
