<?php

namespace app\modules\bot\controllers\groups;

use app\modules\bot\components\Controller;
use app\modules\bot\controllers\privates\GroupGuestController;
use app\modules\bot\models\ChatMember;
use app\modules\bot\models\User;
use Yii;

/**
 * Class BanController
 *
 * @package app\modules\bot\controllers\groups
 */
class BanController extends Controller
{
    /**
     * @return array
     */
    public function actionIndex()
    {
        $telegramUser = $this->getTelegramUser();
        $chat = $this->getTelegramChat();

        $chatMember = ChatMember::findOne([
            'chat_id' => $chat->id,
            'user_id' => $telegramUser->id,
        ]);

        if ($chatMember->isActiveAdministrator()) {
            if ($this->getMessage()->getText() !== null) {
                if ($replyMessage = $this->getMessage()->getReplyToMessage()) {
                    $replyUser = User::findOne([
                        'provider_user_id' => $replyMessage->getFrom()->getId(),
                    ]);

                    if ($replyUser) {
                        $replyChatMember = ChatMember::findOne([
                            'chat_id' => $chat->id,
                            'user_id' => $replyUser->id,
                        ]);
                    }

                    if (!isset($replyChatMember) || !$replyChatMember->isAdministrator()) {
                        // delete replyMessage
                        $this->getBotApi()->deleteMessage(
                            $chat->getChatId(),
                            $this->getMessage()->getReplyToMessage()->getMessageId()
                        );

                        // Kick member from the group
                        $this->getBotApi()->kickChatMember(
                            $chat->chat_id,
                            $replyUser->provider_user_id
                        );

                        $replyUser->sendMessage(
                            $this->render('/privates/warning-ban-chat-member', [
                                'chat' => $chat,
                            ]),
                            [
                                [
                                    [
                                        'callback_data' => GroupGuestController::createRoute('view', [
                                            'id' => $chat->id,
                                        ]),
                                        'text' => Yii::t('bot', 'Group View'),
                                    ],
                                ],
                            ]
                        );
                    }

                }

                // delete /ban command
                $this->getBotApi()->deleteMessage(
                    $chat->getChatId(),
                    $this->getMessage()->getMessageId()
                );
            }
        }

        return [];
    }
}