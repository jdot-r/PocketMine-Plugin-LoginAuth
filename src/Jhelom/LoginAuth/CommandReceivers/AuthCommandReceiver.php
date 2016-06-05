<?php

namespace Jhelom\LoginAuth\CommandReceivers;

use Jhelom\LoginAuth\CommandInvoker;
use Jhelom\LoginAuth\ICommandReceiver;
use Jhelom\LoginAuth\Main;
use pocketmine\command\CommandSender;

class AuthCommandReceiver implements ICommandReceiver
{
    // サブコマンド用のインボーカー
    private $subInvoker;

    /*
     * コンストラクタ
     */
    public function __construct()
    {
        $this->subInvoker = new CommandInvoker();

        // サブコマンドを登録
        $this->subInvoker->add(new UnregisterCommandReceiver());
    }

    /*
     * コマンドの名前
     */
    public function getName() : string
    {
        return "auth";
    }

    /*
     * コンソール実行許可
     */
    public function isAllowConsole() : bool
    {
        return true;
    }

    /*
     * プレイヤー実行許可
     */
    public function isAllowPlayer() : bool
    {
        return false;
    }

    /*
     * OPのみ実行許可
     */
    public function isAllowOpOnly(): bool
    {
        return true;
    }

    /*
     * 実行
     */
    public function execute(CommandInvoker $invoker, CommandSender $sender, array $args)
    {
        if (!$this->subInvoker->invoke($sender, $args)) {
            Main::getInstance()->sendMessageResource($sender, ["authUsage", "authUsage2"]);
        }
    }
}