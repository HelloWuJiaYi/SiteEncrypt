<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 全站加密插件：
 * 
 * 这个插件允许你为整个博客设置一个访问密码，只有输入正确的密码后才能查看内容。
 * 
 * @package SiteEncrypt
 * @author 吴佳轶
 * @version 1.0.0
 * @link https://www.wujiayi.vip
 */

class SiteEncrypt_Plugin implements Typecho_Plugin_Interface
{
    // 激活插件
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Archive')->header = array('SiteEncrypt_Plugin', 'checkAccess');
        return _t('插件已激活，记得设置访问密码！');
    }

    // 禁用插件
    public static function deactivate()
    {
    }

    // 插件配置面板
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $password = new Typecho_Widget_Helper_Form_Element_Password('password', NULL, '', _t('访问密码'), _t('请输入访问博客的密码'));
        $form->addInput($password);
    }

    // 个人用户的配置面板
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    // 检查访问权限
    public static function checkAccess()
    {
        session_start();
        $options = Typecho_Widget::widget('Widget_Options');
        $password = $options->plugin('SiteEncrypt')->password;
        $errorMessage = '';

        // 检查是否已通过认证
        if (!isset($_SESSION['site_encrypt_passed']) || $_SESSION['site_encrypt_passed'] !== true || (time() - $_SESSION['site_encrypt_time']) > 86400) {
            if (isset($_POST['site_encrypt_password'])) {
                if ($_POST['site_encrypt_password'] === $password) {
                    $_SESSION['site_encrypt_passed'] = true;
                    $_SESSION['site_encrypt_time'] = time();
                } else {
                    $errorMessage = '密码错误，请重新输入。';
                }
            }
            
            if (!isset($_SESSION['site_encrypt_passed']) || $_SESSION['site_encrypt_passed'] !== true) {
                // 显示密码输入表单
                echo '<style>
                        .encrypt-form { max-width: 400px; margin: 100px auto; padding: 20px; border: 1px solid #ccc; border-radius: 5px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
                        .encrypt-form label { display: block; margin-bottom: 8px; font-weight: bold; }
                        .encrypt-form input[type="text"] { width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 3px; }
                        .encrypt-form input[type="submit"] { padding: 10px 15px; background-color: #007bff; color: #fff; border: none; border-radius: 3px; cursor: pointer; }
                        .encrypt-form input[type="submit"]:hover { background-color: #0056b3; }
                        .error-message { color: red; margin-bottom: 10px; }
                      </style>
                      <form method="post" class="encrypt-form">
                        <div class="error-message">' . $errorMessage . '</div>
                        <label>请输入访问密码：</label>
                        <input type="text" name="site_encrypt_password" required />
                        <input type="submit" value="提交" />
                      </form>';
                exit; // 停止页面输出
            }
        }
    }
}
