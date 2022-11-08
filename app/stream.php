<?php
// обявление констант для БД
define('DB_HOST', '192.168.100.222');
define('DB_NAME', 'micesystem');
define('DB_USER', 'micesystem');
define('DB_PASS', 'Hu76[,cGF');
define('CDN_STREAM_LIST',  
    array(  '1' => 'u0o3rwhx3q_stream1',
            '2' => 'ygfbbn5cy5_stream2',
            '3' => '7jf5aop1v5_stream3',
            '4' => '3pkb7eji0q_stream4',
            '5' => 'bhr0hm5wx5_stream5',
            '6' => 'kp0i6e85dv_stream6')
    );

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
    setcookie("phoneMobile", "anonymous", time() + 172800);
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
    exit("Трансляция не запущена");
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
$stmt = $mysqli->prepare("SELECT COUNT(DISTINCT room) as roomCount FROM dp_timeline WHERE eventId = ? LIMIT 1");
$stmt->bind_param("i", $user['eventId']);
if (!$stmt->execute()) {
    exit("Проблемы с подключением к Базе Данных. Попробуйте ещё раз.");
}
$roomCount = $stmt->get_result()->fetch_all(MYSQLI_ASSOC)[0]['roomCount'];
$stmt->close();
// отключение от БД
$mysqli->close();

if (isset($_GET['hall']) && $roomCount > 1){
    switch ($_GET['hall']){
        default:
        case 1:
            $event['stream'] = 1;
            break;
        case 2:
            $event['stream'] = 2;
            break;
        case 3:
            $event['stream'] = 3;
            break;
        case 4:
            $event['stream'] = 4;
            break;
        case 5:
            $event['stream'] = 5;
            break;
        case 6:
            $event['stream'] = 6;
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="ru-RU">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= $event['themes'] ?></title>
    <style>
        * {
            padding: 0;
            margin: 0;
            box-sizing: border-box;
        }
        html, body {
            font-family: "Montserrat", sans-serif, sans-serif;
            letter-spacing: normal;
            font-size: 16px;
            background-color: #FBFBFF;
            overflow: hidden;
        }
        h1 {
            margin: 15px 0;
            text-align: center;
        }
        .icon-elem1 {
            position: absolute;
            top: 5rem;
            left: 5.375rem;
            width: 13.1875rem;
            height: 26.5625rem;
            background: transparent url(https://stream.micepartner.ru/assets/i/elem5.svg) center center no-repeat;
            background-size: 100% auto;
            display: block;
        }
        .icon-elem2 {
            position: absolute;
            right: -2.82%;
            top: 6.25rem;
            width: 29.75rem;
            height: 14.1875rem;
            background: transparent url(https://stream.micepartner.ru/assets/i/elem2.svg) center center no-repeat;
            background-size: 100% auto;
            display: block;
        }
        .icon-elem3 {
            position: absolute;
            bottom: -1.25rem;
            right: -2.5rem;
            width: 120rem;
            height: 50.4375rem;
            background: transparent url(https://stream.micepartner.ru/assets/i/elem3.svg) center center no-repeat;
            background-size: 100% auto;
            display: block;
        }
        .loading {
            display: flex;
            flex-wrap: nowrap;
            align-content: center;
            align-items: center;
            justify-content: center;
        }
        .wrapper {
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            flex-wrap: nowrap;
        }
        .d-none {
            display: none;
        }
        .header {
            z-index: 1;
            width: 100vw;
            display: flex;
            justify-content: space-around;
            padding: 1.8125rem 0 0;
            box-shadow: 0px 12px 34px -22px rgb(0 0 0 / 25%);
            background-color: #fff;
        }
        .header__container {
            display: flex;
            margin: 0 auto;
            width: 76.875rem;
            min-width: 320px;
            padding: 0 0.9375rem;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-start;
        }
        .logo {
            font-style: normal;
            font-weight: bold;
            font-size: 1rem !important;
            line-height: 1.8125rem;
            display: flex;
            align-items: center;
            text-decoration: none;
            cursor: pointer;
        }
        .header .logo {
            transform: translateY(-0.5625rem);
        }
        .link {
            color: #1D2A3E;
            display: block;
            text-decoration: none;
            font-style: normal;
            font-weight: normal;
            font-size: 1rem;
            line-height: 150%;
        }
        .link:hover {
            text-decoration: underline;
        }
        .menu {    
            list-style: none;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
        }
        .menu__item {
            margin-right: 2.5rem;
        }
        .menu__item_link {
            position: relative;
            display: block;
            padding: 0px 0px 1.875rem 0px;
            text-decoration: none;
            font-style: normal;
            font-weight: normal;
            font-size: 1rem;
            text-align: center;
        }
        .menu__item_link::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0%;
            height: 0.0625rem;
            transition: width .3s ease-in;
        }
        .menu__item_link:hover::before {
            width: 100%;
        }
        .menu .is-active {
            font-weight: bold;
        }
        .menu .is-active::before {
            height: 0.25rem;
            width: 100%;
        }
        .article {
            display: block;
            margin: 0 auto;
            width: 76.875rem;
            min-width: 320px;
            padding: 0 0.9375rem;
        }
        .buttons {
            display: flex;
            justify-content: center;
        }
        .button {
            max-width: 15rem;
            margin: 0 0.625rem;
            width: 19.4375rem;
            border: 1px solid;
            position: relative;
            box-sizing: border-box;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            align-items: center;
            width: 17.5rem;
            height: 3rem;
            outline: none;
            text-decoration: none;
            border-radius: 1.5625rem;
            font-style: normal;
            font-weight: bold;
            font-size: 0.875rem;
            line-height: 1.0625rem;
            text-align: center;
            color: #fff;
            transition: color .3s ease, background .3s ease;
            cursor: pointer;
        }
        .button:hover {
            color: #fff;
        }
        .message {
            font-weight: bold;
            font-style: normal;
            font-size: 1rem;
            line-height: 130%;
            text-align: center !important;
            margin-bottom: 20px;
        }
        .video {
            margin-top: 2.5rem;
            width: 56.25rem;
            height: auto;
            margin-bottom: 5.125rem;
            position: relative;
            margin: 1.25rem auto;
            overflow: hidden;
            cursor: pointer;
        }
        .footer {
            padding: 6.25rem 0 2.1875rem;
        }
        .footer__container {
            display: flex;
            align-items: center;
            justify-content: space-evenly;
            flex-wrap: wrap;
            margin: 0 auto;
            width: 76.875rem;
            min-width: 320px;
            padding: 0 0.9375rem;
        }
        .footer .logo {
            margin: 0 3.75rem;
        }
    </style>
    <!-- COLORS -->
    <style>
        :root {
            --main-color: #EE656D;
            --link-color: #1D2A3E;
        }
        h1 {
            color: var(--main-color);
        }
        .logo {
            color: var(--main-color);
        }
        .message {   
            color: var(--main-color);
        }
        .menu__item_link {
            color: var(--link-color);
        }
        .menu__item_link::before {
            background: var(--main-color);
        }
        .menu .is-active {
            color: var(--main-color);
        }
        .button {
            border-color: var(--main-color);
            color: var(--main-color);
        }
        .button:hover {
            background: var(--main-color);
        }
    </style>
</head>

<body>
    <i class="icon-elem1"></i>
    <i class="icon-elem2"></i>
    <i class="icon-elem3"></i>
    <div id="root">
        <div class="loading" style="height: 100vh;">
            <img src="https://i.stack.imgur.com/hzk6C.gif" alt="loading...">
        </div>
    </div>
</body>
<script src="https://vjs.zencdn.net/7.15.4/video.min.js"></script>
<!-- Загрузим React. -->
<script src="https://unpkg.com/@babel/standalone/babel.min.js" crossorigin></script>
<script src="https://unpkg.com/react/umd/react.development.js" crossorigin></script>
<script src="https://unpkg.com/react-dom/umd/react-dom.development.js" crossorigin></script>
<!-- <script src="https://unpkg.com/react/umd/react.production.min.js" crossorigin></script>
<script src="https://unpkg.com/react-dom/umd/react-dom.production.min.js" crossorigin></script> -->

<script type="text/babel">
    "use strict";
    const api     = '<?=md5(implode(' ', array($user['surname'], $user['name'], $user['fathername']))."salt")?>';
    const author  = '<?=implode(' ', array($user['surname'], $user['name'], $user['fathername']))?>';
    const uid     = <?=$user['id']?>;
    const eid     = <?=$event['id']?>;
    const room    = <?=$event['stream']?>;
    const roomCount = <?=$roomCount?>;
    const cdn     = '<?=CDN_STREAM_LIST[$event['stream']]?>';
    const root    = ReactDOM.createRoot(document.getElementById("root"));
    top.player  = null;
    top.control   = true;
    top.wsSend    = null;

    function wsConnect(setStage, setUpMessage, setMessages) {
        let messages = [];
        let wss = new WebSocket('wss://chat.micepartner.ru/ws' + room + '/');
        wss.onopen = function() {
            top.webSocketError = 0;
            console.log('wss://chat.micepartner.ru/ws' + room + '/ connected');
            setStage('open');
        };

        top.wsSend = function(text) {
            try{
                if (text != '') {
                    let jsonMessage = JSON.stringify({action: 'NEW', api: api, uid: uid, author: author, pid: 0, text: text});
                    wss.send(jsonMessage);
                }
            } catch (err) {console.log(err)}
        }
        
        wss.onmessage = function (serverMessage) {
            try {
                const message = JSON.parse(serverMessage.data);
                //console.log('DEBUG', message); // DEBUG 
                switch (message.action) {
                    case 'NEW':
                        messages = messages.concat(message);
                        setMessages(messages);
                        break;
                    case 'DELETE':
                        let delIndex = messages.findIndex((element) => element.id == message.id);
                        messages.splice(delIndex,1);
                        messages = messages.slice();
                        setMessages(messages);
                        break;
                    case 'CONTROL':
                        top.control = message.control === 'true';
                        break;
                    case 'UPMESSAGE':
                        setUpMessage(message.upMessage);
                        break;
                    case 'SHUTDOWN':
                        window.location.reload();
                        break;
                    case 'ERROR':
                        console.log('ERROR: ',serverMessage.data);
                        break;
                }
            } catch (err) {console.log(serverMessage.data, err)}
        }

        wss.onclose = function(e) {
            messages = [];
            top.webSocketError = e.code;
            console.log('Socket is closed. Reconnect will be attempted in 1 second.', e.code, e.reason);
            setTimeout(function() {
                wsConnect(setStage, setUpMessage, setMessages);
            }, 1000);
            setStage('close');
        };

        wss.onerror = function(err) {
            console.error('Socket encountered error: ', err.message, 'Closing socket');
            wss.close();
        };
    }



    const Header = () => {
        return(
            <header className={'header'}>
                <div className={'header__container'}>
                    <a className={'logo'} href="/">Stream</a>
                    <nav className={'nav'}>
                        <ul className={'menu'}>
                            <li className={'menu__item'}><a className={'menu__item_link'} href="/">Главная</a></li> 
                            <li className={'menu__item'}><a className={'menu__item_link'} href="/prog_page">Программа</a></li>
                            <li className={'menu__item'}><a className={'menu__item_link is-active'} href="/stream_page">Трансляция</a></li>
                            <li className={'menu__item'}><a className={'menu__item_link'} href="/faq_page">Частые вопросы</a></li>
                            <li className={'menu__item'}><a className={'menu__item_link'} href="/user_page">Личный кабинет</a></li>
                        </ul>
                    </nav>
                </div>
            </header>
        );
    }

    const Article = (props) => {
        const [stage, setStage] = React.useState('loading');
        const [upMessage, setUpMessage] = React.useState('');
        switch (stage) {
            default:
            case 'loading':
                wsConnect(setStage, setUpMessage, ()=>{});
                return(
                    <article className={'article'}>
                        <div className={'loading'}>
                            <img src="https://i.stack.imgur.com/hzk6C.gif"  alt="loading..." />
                        </div>
                    </article>
                    );
            case 'open':
                return(
                    <article className={'article'}>
                        <Video roomCount={props.roomCount} room={props.room} cdn={props.cdn} upMessage={upMessage}/>
                    </article>
                    );
            case 'close':
                return(
                    <article className={'article'}>
                        <Video roomCount={props.roomCount} room={props.room} cdn={props.cdn} upMessage={upMessage}/>
                    </article>
                    );
        }
    }    
    
    const Video = (props) => {
        React.useEffect(() => {
            top.player = videojs('mice-video');
        });
        let buttons = [];
        let src = 'https://3kgq2rbjoj5.a.trbcdn.net/livemaster/' + props.cdn + '/playlist.m3u8'
        if (props.roomCount > 1) {
            for(let i = 1; i <= props.roomCount; i++){
                buttons = buttons.concat([<Button key={i} i={i} />]);
            }
        }
        return(
            <article className={'article'}>
                <div className={'buttons'}>{buttons}</div>
                <h1>{'ЗАЛ №' + props.room}</h1>
                <p className={'message'}>Уважаемые слушатели трансляции!<br />При выполнении условий коды НМО будут загружены в личный кабинет <a href="https://my.micepartner.ru/">my.micepartner.ru</a> в течение 3 рабочих дней!</p>
                <p className={'message'}>{props.upMessage}</p>
                <div className={'video'}>
                    <video id={"mice-video"} controls={false} onContextMenu={() => false} className={"video-js vjs-default-skin"} data-setup='{ "controls": true, "fluid": true, "autoplay": true}' poster="https://stream.micepartner.ru/assets/img/back.jpg" autoPlay playsInline><source src={src} type="application/x-mpegURL" /></video>
                </div>
            </article>
            );
    }


    const Footer = () => {
        return(
        <footer className={'footer'}>
            <div className={'footer__container'}>
                <a href="mailto:help@micepartner.ru" className={'link'}>help@micepartner.ru</a>
                <a href="/" className={'logo'}>MicePartner</a>
                <a href="tel+78462193312" className={'link'}>+7 (846) 219-33-12</a>
            </div>
        </footer>);
    }

    const Button = (props) => {
        let link = '/stream.php?hall='+props.i;
        let name = 'ЗАЛ №'+props.i;
        return(<a className={'button'} href={link}>{name}</a>)
    }
    
    root.render(
        <div className={'wrapper'}>
        <Header />
        <Article roomCount={roomCount} room={room} cdn={cdn}/>
        <Footer />
        </div>);
</script>

</html>