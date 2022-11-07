<?php
// обявление констант для БД
define('DB_HOST', '192.168.100.222');
define('DB_NAME', 'micesystem');
define('DB_USER', 'micesystem');
define('DB_PASS', 'Hu76[,cGF');

// Подключение к БД
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
/* проверка соединения */
if ($mysqli->connect_errno) {
    exit("Не удалось подключиться: " . $mysqli->connect_error);
}

$mysqli->query("SET names utf8") === TRUE;

/* DEBUG LOG */
$json_server = json_encode($_SERVER, JSON_UNESCAPED_UNICODE + JSON_HEX_TAG + JSON_HEX_AMP + JSON_HEX_APOS + JSON_HEX_QUOT);
$json_cookie = json_encode($_COOKIE, JSON_UNESCAPED_UNICODE + JSON_HEX_TAG + JSON_HEX_AMP + JSON_HEX_APOS + JSON_HEX_QUOT);
$json_get = json_encode($_GET, JSON_UNESCAPED_UNICODE + JSON_HEX_TAG + JSON_HEX_AMP + JSON_HEX_APOS + JSON_HEX_QUOT);
$json_post = json_encode($_POST, JSON_UNESCAPED_UNICODE + JSON_HEX_TAG + JSON_HEX_AMP + JSON_HEX_APOS + JSON_HEX_QUOT);

$stmt = $mysqli->prepare("INSERT INTO fa_stream_debug_logs (json_server, json_cookie, json_get, json_post) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $json_server, $json_cookie, $json_get, $json_post);
if (!$stmt->execute()) {
    exit("Проблемы с подключением к Базе Данных. Попробуйте ещё раз.");
}
$stmt->close();
/* END DEBUG LOG */

// logout - чистит куки и отправляет логинится
function logout()
{
    setcookie("phoneMobile", "anonimus", time() + 172800);
    setcookie("userId", "0", time() + 172800);
    header("location:login");
    exit("Не авторизованы.");
}

// если нет нужных куков -> logout
if (!isset($_COOKIE['phoneMobile']) || !isset($_COOKIE['userId']) || $_COOKIE['userId'] <= 0) {
    logout();
}

// санитайз куков
$cookiePhoneMobile = filter_var($_COOKIE['phoneMobile'], FILTER_SANITIZE_NUMBER_INT);
$cookieUserId = intval(filter_var($_COOKIE['userId'], FILTER_SANITIZE_NUMBER_INT));

// ищем пользователя в БД
$stmt = $mysqli->prepare("SELECT * FROM dp_users du WHERE id = ? AND phoneMobile = ? LIMIT 1");
$stmt->bind_param("is", $cookieUserId, $cookiePhoneMobile);
if (!$stmt->execute()) {
    exit("Проблемы с подключением к Базе Данных. Попробуйте ещё раз.");
}
$stmtResult = $stmt->get_result();
// если нет -> logout
if ($stmtResult->num_rows <= 0) {
    logout();
}
// если есть, сохранить в $user
$user = $stmtResult->fetch_all(MYSQLI_ASSOC)[0];
$stmt->close();

// если нет мероприятия отправляем выбирать мероприятие
if ($user['eventId'] <= 0) {
    header("location:events");
    exit("Не зарегистрирован на мероприятие");
}

$stmt = $mysqli->prepare("SELECT * FROM dp_events de WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user['eventId']);
if (!$stmt->execute()) {
    exit("Проблемы с подключением к Базе Данных. Попробуйте ещё раз.");
}
$event = $stmt->get_result()->fetch_all(MYSQLI_ASSOC)[0];
$stmt->close();
// если нет stream ждем минуту и по новой
if ($event['stream'] == 0) {
    header("Refresh:60");
    exit("Мероприятие ещё не началось");
}
// если статус 0 то ставим 9
if ($user['eventStatus'] == 0) {
    $stmt = $mysqli->prepare("UPDATE dp_users SET eventStatus = 9 WHERE id = ?");
    $stmt->bind_param("i", $user['id']);
    if (!$stmt->execute()) {
        exit("Проблемы с подключением к Базе Данных. Попробуйте ещё раз.");
    }
    $stmt->close();
}
// отключение от БД
$mysqli->close();
?>

<!DOCTYPE html>
<html lang="ru-RU">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= $event['themes'] ?></title>
    <style>
        html, body {
            font-family: "Montserrat", sans-serif, sans-serif;
            padding: 0;
            margin: 0;
        }
        .wrapper {
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            flex-wrap: nowrap;
        }
        .fixed {
            display: block;
            position: fixed;
        }
        .header {
            width: 100vw;
            display: flex;
            justify-content: space-around;
        }
        .logo {
            margin: 0;
            padding: 10px;
            font-style: normal;
            font-weight: bold;
            font-size: 1rem !important;
            line-height: 1.8125rem;
            display: flex;
            align-items: center;
            text-decoration: none;
            transform: translateY(-0.5625rem);
            cursor: pointer;
        }
        .menu {
            list-style: none;
            display: flex;
            margin: 0;
        }
        .menu__item {
            margin: 0;
            padding: 10px;
        }
        .article {
            overflow-y: scroll;
            width: 100vw;
        }
    </style>
    <style>
        .logo {
            color: #EE656D;
        }
    </style>
</head>

<body>
    <div id="root"></div>
</body>

<!-- Загрузим React. -->
<script src="https://unpkg.com/@babel/standalone/babel.min.js" crossorigin></script>
<script src="https://unpkg.com/react/umd/react.development.js" crossorigin></script>
<script src="https://unpkg.com/react-dom/umd/react-dom.development.js" crossorigin></script>
<!-- <script src="https://unpkg.com/react/umd/react.production.min.js" crossorigin></script>
<script src="https://unpkg.com/react-dom/umd/react-dom.production.min.js" crossorigin></script> -->

<script type="text/babel">
    "use strict";
    const root = ReactDOM.createRoot(document.getElementById("root"));

    const Header = () => {
        return(
            <header className={'header'}>
                <a className={'logo'} href="/">Stream</a>
                <nav className={'nav'}>
                    <ul className={'menu'}>
                        <li className={'menu__item'}><a href="/">Главная</a></li> 
                        <li className={'menu__item'}><a href="/prog_page">Программа</a></li>
                        <li className={'menu__item'}><a href="/stream_page">Трансляция</a></li>
                        <li className={'menu__item'}><a href="/faq_page">Частые вопросы</a></li>
                        <li className={'menu__item'}><a href="/user_page">Личный кабинет</a></li>
                    </ul>
                </nav>
            </header>
        );
    }

    const Video = () => {return(<article className={'article'}>article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br />article<br /></article>);}
    const Footer = () => {return(<footer>footer</footer>);}
    const Chat = () => {return(<div className={'fixed'}>fixed</div>);}
    const Control = () => {return(<div className={'fixed'}>fixed</div>);}

    
    root.render(<div><div className={'wrapper'}><Header /><Video /><Footer /></div><Chat /><Control /></div>);

</script>

</html>