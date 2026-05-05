<?php

namespace TypechoPlugin\SiteEncrypt;

use Throwable;
use Typecho\Plugin as TypechoPlugin;
use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Password;
use Typecho\Widget\Helper\Form\Element\Select;
use Widget\Archive;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 全站加密插件：
 *
 * <a target="_blank" href="https://github.com/HelloWuJiaYi/SiteEncrypt" rel="noopener noreferrer">SiteEncrypt</a>
 * 允许你为整个博客设置一个访问密码，只有输入正确的密码后才能查看内容。
 *
 * @package SiteEncrypt
 * @author 吴佳轶
 * @version 1.1.0
 * @link https://www.wujiayi.vip
 */
class Plugin implements PluginInterface
{
    private const DEFAULT_EXPIRE_DAYS = 1;
    private const MAX_EXPIRE_DAYS = 7;

    private const SESSION_KEY = 'site_encrypt_passed';
    private const SESSION_TIME_KEY = 'site_encrypt_time';

    public static function activate()
    {
        TypechoPlugin::factory(Archive::class)->handleInit = [self::class, 'checkAccess'];
        return _t('插件已激活，记得设置访问密码。');
    }

    public static function deactivate()
    {
    }

    public static function config(Form $form)
    {
        $password = new Password(
            'password',
            null,
            '',
            _t('访问密码'),
            _t('请输入访问博客所需的密码。留空则不启用全站加密。')
        );

        $form->addInput($password);

        $expireOptions = [];

        for ($i = 1; $i <= self::MAX_EXPIRE_DAYS; $i++) {
            $expireOptions[(string) $i] = $i . ' 天';
        }

        $expireDays = new Select(
            'expireDays',
            $expireOptions,
            (string) self::DEFAULT_EXPIRE_DAYS,
            _t('登录有效期'),
            _t('设置输入密码后的有效时间，单位为天，最多 7 天。')
        );

        $form->addInput($expireDays);
    }

    public static function personalConfig(Form $form)
    {
    }

    public static function checkAccess(...$args)
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $password = self::getPassword();

        if ($password === '') {
            return;
        }

        self::startSession();

        if (self::hasPassed()) {
            return;
        }

        $errorMessage = '';

        if (
            ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && isset($_POST['site_encrypt_password'])
        ) {
            $inputPassword = trim((string) $_POST['site_encrypt_password']);

            if (hash_equals($password, $inputPassword)) {
                $_SESSION[self::SESSION_KEY] = true;
                $_SESSION[self::SESSION_TIME_KEY] = time();

                session_write_close();

                self::renderRedirectPage();
                exit;
            }

            $errorMessage = '密码错误，请重新输入。';
        }

        self::renderLoginPage($errorMessage);
        exit;
    }

    private static function startSession(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        session_set_cookie_params([
            'lifetime' => self::getExpireSeconds(),
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    private static function hasPassed(): bool
    {
        if (
            !isset($_SESSION[self::SESSION_KEY])
            || !isset($_SESSION[self::SESSION_TIME_KEY])
        ) {
            return false;
        }

        if ($_SESSION[self::SESSION_KEY] !== true) {
            return false;
        }

        $passedTime = (int) $_SESSION[self::SESSION_TIME_KEY];

        return (time() - $passedTime) <= self::getExpireSeconds();
    }

    private static function getPassword(): string
    {
        try {
            $pluginConfig = Options::alloc()->plugin('SiteEncrypt');

            return isset($pluginConfig->password)
                ? trim((string) $pluginConfig->password)
                : '';
        } catch (Throwable $e) {
            return '';
        }
    }

    private static function getExpireDays(): int
    {
        try {
            $pluginConfig = Options::alloc()->plugin('SiteEncrypt');

            $days = isset($pluginConfig->expireDays)
                ? (int) $pluginConfig->expireDays
                : self::DEFAULT_EXPIRE_DAYS;

            if ($days < 1) {
                return self::DEFAULT_EXPIRE_DAYS;
            }

            if ($days > self::MAX_EXPIRE_DAYS) {
                return self::MAX_EXPIRE_DAYS;
            }

            return $days;
        } catch (Throwable $e) {
            return self::DEFAULT_EXPIRE_DAYS;
        }
    }

    private static function getExpireSeconds(): int
    {
        return self::getExpireDays() * 86400;
    }

    private static function getSiteTitle(): string
    {
        try {
            $title = Options::alloc()->title ?? 'Typecho';
            $title = trim((string) $title);

            return $title !== '' ? $title : 'Typecho';
        } catch (Throwable $e) {
            return 'Typecho';
        }
    }

    private static function getCurrentUrl(): string
    {
        $url = $_SERVER['REQUEST_URI'] ?? '/';
        $url = str_replace(["\r", "\n"], '', $url);

        return $url !== '' ? $url : '/';
    }

    private static function renderRedirectPage(): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
            header('X-Robots-Tag: noindex, nofollow', true);
        }

        $url = self::getCurrentUrl();
        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $jsonUrl = json_encode($url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        echo '<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta http-equiv="refresh" content="0;url=' . $safeUrl . '">
<title>正在进入</title>
<script>
window.location.replace(' . $jsonUrl . ');
</script>
<style>
html,
body {
    margin: 0;
    padding: 0;
    min-height: 100%;
}

body {
    background: #f6f6f3;
    color: #666;
    font: 14px/1.7 -apple-system, BlinkMacSystemFont, "Segoe UI", "Helvetica Neue", Arial, "PingFang SC", "Microsoft YaHei", sans-serif;
}

.redirect-wrap {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 32px 16px;
    box-sizing: border-box;
}

.redirect-box {
    width: 320px;
    padding: 24px;
    text-align: center;
    background: #fff;
    border: 1px solid #d9d9d6;
    border-radius: 2px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
}

.redirect-title {
    margin: 0 0 8px;
    color: #555;
    font-size: 18px;
    font-weight: normal;
}

.redirect-desc {
    margin: 0;
    color: #999;
    font-size: 13px;
}
</style>
</head>
<body>
<div class="redirect-wrap">
    <div class="redirect-box">
        <h1 class="redirect-title">验证成功</h1>
        <p class="redirect-desc">正在进入网站……</p>
    </div>
</div>
</body>
</html>';
    }

    private static function renderLoginPage(string $errorMessage = ''): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
            header('X-Robots-Tag: noindex, nofollow', true);
        }

        $siteTitle = self::getSiteTitle();
        $siteTitleHtml = htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8');

        $errorHtml = '';

        if ($errorMessage !== '') {
            $errorHtml = '<div class="typecho-message typecho-message-error">'
                . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8')
                . '</div>';
        }

        echo '<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>访问验证 - ' . $siteTitleHtml . '</title>
<style>
html,
body {
    margin: 0;
    padding: 0;
    min-height: 100%;
}

body {
    background: #f6f6f3;
    color: #444;
    font: 14px/1.7 -apple-system, BlinkMacSystemFont, "Segoe UI", "Helvetica Neue", Arial, "PingFang SC", "Microsoft YaHei", sans-serif;
}

.typecho-login-wrap {
    min-height: 100vh;
    padding: 48px 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
}

.typecho-login-box {
    width: 360px;
    max-width: 100%;
}

.typecho-login-logo {
    margin: 0 0 22px;
    text-align: center;
    color: #555;
    font-size: 26px;
    font-weight: normal;
    line-height: 1.3;
}

.typecho-login-logo span {
    display: block;
    margin-top: 6px;
    color: #999;
    font-size: 13px;
}

.typecho-login-panel {
    padding: 24px;
    background: #fff;
    border: 1px solid #d9d9d6;
    border-radius: 2px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
}

.typecho-login-panel p {
    margin: 0 0 16px;
}

.typecho-login-panel label {
    display: block;
    margin-bottom: 7px;
    color: #555;
    font-weight: bold;
}

.typecho-input {
    display: block;
    width: 100%;
    height: 38px;
    padding: 7px 9px;
    color: #444;
    background: #fff;
    border: 1px solid #d9d9d6;
    border-radius: 2px;
    box-sizing: border-box;
    font-size: 14px;
    outline: none;
}

.typecho-input:focus {
    border-color: #467b96;
    box-shadow: 0 0 0 2px rgba(70, 123, 150, .12);
}

.typecho-button-row {
    margin-top: 20px !important;
    margin-bottom: 0 !important;
}

.typecho-button {
    display: block;
    width: 100%;
    height: 38px;
    padding: 0 16px;
    border: 1px solid #3f6f87;
    border-radius: 2px;
    background: #467b96;
    color: #fff;
    font-size: 14px;
    line-height: 36px;
    text-align: center;
    cursor: pointer;
    box-sizing: border-box;
}

.typecho-button:hover,
.typecho-button:focus {
    background: #3f6f87;
    border-color: #365f74;
}

.typecho-message {
    margin: 0 0 16px;
    padding: 9px 12px;
    border-radius: 2px;
    font-size: 13px;
}

.typecho-message-error {
    color: #8a1f11;
    background: #fff6f4;
    border: 1px solid #f1c1b8;
}

.typecho-login-footer {
    margin-top: 16px;
    text-align: center;
    color: #999;
    font-size: 12px;
}

@media (max-width: 480px) {
    .typecho-login-wrap {
        align-items: flex-start;
        padding-top: 32px;
    }

    .typecho-login-logo {
        font-size: 24px;
    }

    .typecho-login-panel {
        padding: 22px 20px;
    }
}
</style>
</head>
<body>
<div class="typecho-login-wrap">
    <div class="typecho-login-box">
        <h1 class="typecho-login-logo">
            ' . $siteTitleHtml . '
            <span>请输入访问密码继续浏览</span>
        </h1>

        <form method="post" class="typecho-login-panel" autocomplete="off">
            ' . $errorHtml . '

            <p>
                <label for="site_encrypt_password">访问密码</label>
                <input
                    type="password"
                    id="site_encrypt_password"
                    name="site_encrypt_password"
                    class="typecho-input"
                    required
                    autofocus
                    autocomplete="current-password"
                >
            </p>

            <p class="typecho-button-row">
                <button type="submit" class="typecho-button">登录</button>
            </p>
        </form>

        <div class="typecho-login-footer">
            Powered by wujiayi.vip
        </div>
    </div>
</div>
</body>
</html>';
    }
}
