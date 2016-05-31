<?php
namespace LoginAuth;

use pocketmine\utils\Config;

/**
 * メッセージリソース
 *
 * @package LoginAuth
 */
class MessageResource
{
    private $config;

    /**
     * コンストラクタ
     *
     * @param Main $main
     * @param string $lang
     */
    public function __construct(Main $main, string $lang = "ja")
    {
        // 引数をもとにファイルのパスを組み立て
        $file = "messages-" . $lang . ".yml";
        $path = $main->getDataFolder() . $file;

        // ファイルが不在なら
        if (!file_exists($path)) {
            // 日本語ファイルのパスにする
            $file = "messages-ja.yml";
            $path = $main->getDataFolder() . $file;
        }

        // リソースをセーブ
        $main->saveResource($file);

        // リソースをロード
        $this->config = new Config($path, Config::YAML);
    }

    /**
     * メッセージを取得してフォーマットした結果を返す
     *
     * @param string $key
     * @param array|NULL $args
     * @return string
     */
    private function get(string $key, array $args = NULL) : string
    {
        $message = $this->config->get($key);

        if (is_array($args)) {
            foreach ($args as $key => $value) {
                $message = str_replace("{" . $key . "}", $value, $message);
            }
        }

        return $message;
    }

    /**
     * 既にログイン認証済み
     *
     * @return string
     */
    public function alreadyLogin() : string
    {
        return $this->get("alreadylogin");
    }

    /**
     * パスワード未入力
     *
     * @return string
     */
    public function passwordRequired() : string
    {
        return $this->get("passwordRequired");
    }

    public function accountSlotOver1(integer $accountSolt): string
    {
        return $this->get("accountSlotOver1", ["accountSlot" => $accountSolt]);
    }

    public function accountSlotOlver2(): string
    {
        return $this->get("accountSlotOver2");
    }

    public function alreadyExistsName(string $name) : string
    {
        return $this->get("alreadyExistsName", ["name" => $name]);
    }

    public function registerSuccessful(): string
    {
        return $this->get("registerSuccessful");
    }

    public function passwordLengthMin(integer $length) : string
    {
        return $this->get("passwordLengthMin", ["length" => $length]);
    }

    public function passwordLengthMax(integer $length) : string
    {
        return $this->get("passwordLengthMax", ["length" => $length]);
    }

    public function registerFirst(): string
    {
        return $this->get("registerFirst");
    }

    public function loginSuccessful() : string
    {
        return $this->get("loginSuccessful");
    }

    public function passwordChangeSuccessful() : string
    {
        return $this->get("passwordChangeSuccessful");
    }

    public function passwordChangeRequired() : string
    {
        return $this->get("passwordChangeRequired");
    }

    public function unregisterNotFound() : string
    {
        return $this->get("unregisterNotFound");
    }

    public function unregisterPasswordRequired() : string
    {
        return $this->get("unregisterPasswordRequired");
    }


}