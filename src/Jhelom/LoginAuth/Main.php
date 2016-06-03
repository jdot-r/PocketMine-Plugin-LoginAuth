<?php

namespace Jhelom\LoginAuth;

use pocketmine\command\CommandSender;
use pocketmine\level;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class Main extends PluginBase
{
    // データベース
    private $pdo;

    // リスナー
    private $listener;

    // メッセージリソース
    private $messageResource;

    // セキュリティスタンプマネージャー
    private $securityStampManager;

    // データベース初期化SQL
    private $ddl = <<<_SQL_
CREATE TABLE [account] (
[name] TEXT NOT NULL UNIQUE,
[clientId] TEXT NOT NULL,
[ip] TEXT NOT NULL,
[passwordHash] TEXT NOT NULL,
[securityStamp] TEXT NOT NULL,
PRIMARY KEY(name)
);                
_SQL_;

    private static $instance;

    public static function getInstance() : Main
    {
        return self::$instance;
    }

    /**
     * プラグインが有効化されたときのイベント
     */
    public function onEnable()
    {
        $this->getLogger()->info("§a 開発者 jhelom & dragon7");

        // スタティックに代入
        self::$instance = $this;

        // デフォルト設定をセーブ
        $this->saveDefaultConfig();

        // 設定をリロード
        $this->reloadConfig();

        // メッセージリソースをロード
        $this->loadMessageResource($this->getConfig()->get("locale"));

        // セキュリティスタンプマネージャーを初期化
        $this->securityStampManager = new SecurityStampManager();

        // データベースに接続
        $this->openDatabase();

        // プラグインマネージャーに登録してイベントを受信
        $this->listener = new EventListener($this);
        $this->getServer()->getPluginManager()->registerEvents($this->listener, $this);
    }

    /*
     * プラグインが無効化されたときのイベント
     */
    public function onDisable()
    {
    }

    /*
     * メッセージリソースをロード
     */
    private function loadMessageResource(string $locale = NULL)
    {
        // NULLの場合、デフォルトの言語にする
        $locale = $locale ?? "ja";

        // 言語の指定をもとにファイルのパスを組み立て
        $file = "messages-" . $locale . ".yml";
        $path = $this->getDataFolder() . $file;

        // ファイルが不在なら
        if (!file_exists($path)) {
            // 日本語ファイルのパスにする
            $file = "messages-ja.yml";
            $path = $this->getDataFolder() . $file;
        }

        // リソースをセーブ
        $this->saveResource($file);

        // リソースをロード
        $this->messageResource = new Config($path, Config::YAML);
    }

    public function getMessageList(array $keyList, string $prefix = "", array $args = NULL) : string
    {
        $strList = [];

        foreach ($keyList as $key) {
            array_push($strList, $prefix . $this->getMessage($key, $args));
        }

        return implode(PHP_EOL, $strList);
    }

    /*
     * メッセージを取得
     *
     * 引数 args に連想配列を渡すとメッセージの文字列中にプレースフォルダ（波括弧）を置換する
     */
    public function getMessage(string $key, array $args = NULL) : string
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $message = $this->messageResource->get($key);

        if ($message == NULL || $message == "") {
            $this->getLogger()->warning("メッセージリソース不在: " . $key);
            $message = "";
        }

        // args が配列の場合
        if (is_array($args)) {
            // 配列をループ
            foreach ($args as $key => $value) {
                // プレースフォルダを組み立て
                $placeHolder = "{" . $key . "}";

                // プレースフォルダをバリューで置換
                $message = str_replace($placeHolder, $value, $message);
            }
        }

        return $message;
    }

    /**
     * データベースに接続
     */
    private function openDatabase()
    {
        // データベースファイルのパスを組み立て
        $path = rtrim($this->getDataFolder(), "/") . DIRECTORY_SEPARATOR . "account.db";

        // データベースファイルが不在なら、初期化フラグを立てる
        $isInitializing = !file_exists($path);

        // 接続文字列を組み立て
        $connectionString = "sqlite:" . $path;

        // データベースを開く
        $this->pdo = new \PDO($connectionString);

        // SQLエラーで例外をスローするように設定
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // 初期化フラグが立っていたら
        if ($isInitializing) {
            // テーブルを作成
            $this->pdo->exec($this->ddl);
        }
    }

    /*
     * セキュリティスタンプマネージャーを取得
     */
    public function getSecurityStampManager() : SecurityStampManager
    {
        return $this->securityStampManager;
    }

    /*
     * アカウント登録済みなら true を返す
     */
    function isRegistered(Player $player) : bool
    {
        // アカウントを検索
        $account = $this->findAccountByName($player->getName());

        if ($account->isNull) {
            return false;
        } else {
            return true;
        }
    }

    /*
     * 名前をもとにデータベースからアカウントを検索する
     * 不在の場合は isNullフィールドが true のアカウントを返す
     */
    public function findAccountByName(string $name) : Account
    {
        $sql = "SELECT * FROM account WHERE name = :name";
        $stmt = $this->preparedStatement($sql);
        $stmt->bindValue(":name", strtolower($name), \PDO::PARAM_STR);
        $stmt->execute();

        // データベースからクラスとして取得
        $account = $stmt->fetchObject("Jhelom\\LoginAuth\\Account");

        // 検索結果が０件の場合は false なので
        if ($account === false) {
            // isNull が true の Account を返す
            return new Account(true);
        }

        // データベースから取得したクラスを返す
        return $account;
    }

    /*
     * 端末IDをもとにデータベースからアカウントを検索して、Accountクラスの配列を返す
     * 不在の場合は、空の配列を返す
     */
    public function findAccountsByClientId(string $clientId) : array
    {
        $sql = "SELECT * FROM account WHERE clientId = :clientId ORDER BY name";
        $stmt = $this->preparedStatement($sql);
        $stmt->bindValue(":clientId", $clientId, \PDO::PARAM_STR);
        $stmt->execute();

        // データベースからクラスの配列として取得
        $results = $stmt->fetchAll(\PDO::FETCH_CLASS, "Jhelom\\LoginAuth\\Account");

        return $results;
    }

    /*
     * SQLプリペアドステートメント
     */
    public function preparedStatement(string $sql) : \PDOStatement
    {
        return $this->getDatabase()->prepare($sql);
    }

    /*
     * データベースを取得
     */
    private function getDatabase() : \PDO
    {
        return $this->pdo;
    }

    /*
     * 認証済みなら true を返す
     */
    public function isAuthenticated(Player $player) :bool
    {
        // キャッシュを検証
        if ($this->getSecurityStampManager()->validate($player)) {
            // 認証済みを示す true を返す
            return true;
        }

        // 名前をもとにアカウントをデータベースから検索
        $account = $this->findAccountByName(strtolower($player->getName()));

        // アカウントがアカウントが存在しない
        if ($account->isNull) {
            return false;
        }

        // データベースのセキュリティスタンプと比較して違っている
        if ($account->securityStamp !== $this->getSecurityStampManager()->makeStamp($player)) {
            return false;
        }

        // キャッシュに登録
        $this->getSecurityStampManager()->add($player);
        return true;
    }

    /*
     * パスワードを検証、成功なら true、失敗なら false を返す
     */
    public function validatePassword(Player $player, string $password, string $emptyErrorMessage) : bool
    {
        // パスワードが空欄の場合
        if ($password === "") {
            $player->sendMessage(TextFormat::RED . $emptyErrorMessage);
            return false;
        }

        if (!preg_match("/^[a-zA-Z0-9]+$/", $password)) {
            $player->sendMessage(TextFormat::RED . $this->getMessage("passwordFormat"));
            return false;
        }

        // 設定ファイルからパスワードの文字数の下限を取得
        $passwordLengthMin = $this->getConfig()->get("passwordLengthMin");

        // 設定ファイルからパスワードの文字数の上限を取得
        $passwordLengthMax = $this->getConfig()->get("passwordLengthMax");

        // パスワードの文字数を取得
        $passwordLength = strlen($password);

        // パスワードが短い場合
        if ($passwordLength < $passwordLengthMin) {
            $player->sendMessage(TextFormat::RED . $this->getMessage("passwordLengthMin", ["length" => $passwordLengthMin]));
            return false;
        }

        // パスワードが長い場合
        if ($passwordLength > $passwordLengthMax) {
            $player->sendMessage(TextFormat::RED . $this->getMessage("passwordLengthMax", ["length" => $passwordLengthMax]));
            return false;
        }

        return true;
    }

    /*
     * パスワードハッシュを生成する
     */
    public function makePasswordHash(string $password) : string
    {
        return hash("sha256", $password);
    }

    /*
     * CommandSender が Player なら true を返す
     */
    public static function isPlayer(CommandSender $sender) : bool
    {
        return $sender instanceof Player;
    }

    /*
     * CommandSender が Player でなければ true を返す
     */
    public static function isNotPlayer(CommandSender $sender) : bool
    {
        return !self::isPlayer($sender);
    }

    /*
     * CommandSender（基底クラス） を Player（派生クラス）に（タイプヒンティングで疑似的で）ダウンキャストする
     */
    public static function castCommandSenderToPlayer(CommandSender $sender) : Player
    {
        return $sender;
    }
}

?>