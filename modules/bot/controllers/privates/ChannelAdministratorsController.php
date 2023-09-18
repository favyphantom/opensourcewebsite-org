<?php

namespace app\modules\bot\controllers\privates;

use app\modules\bot\components\Controller;
use app\modules\bot\components\helpers\Emoji;
use app\modules\bot\components\helpers\PaginationButtons;
use app\modules\bot\filters\ChannelCreatorAccessFilter;
use app\modules\bot\models\Chat;
use app\modules\bot\models\ChatMember;
use app\modules\bot\models\ChatSetting;
use Yii;
use yii\data\Pagination;

/**
 * Class ChannelAdministratorsController
 *
 * @package app\modules\bot\controllers\privates
 */
class ChannelAdministratorsController extends Controller
{
    public function behaviors()
    {
        return [
            'channelCreatorAccess' => [
                'class' => ChannelCreatorAccessFilter::class,
            ],
        ];
    }

    /**
     * @param int $id Chat->id
     * @param int $page
     * @return array
     */
    public function actionIndex($id = null, $page = 1)
    {
        $chat = Yii::$app->cache->get('chat');

        $this->getState()->clearInputRoute();

        $query = $chat->getHumanAdministrators();

        $pagination = new Pagination([
            'totalCount' => $query->count(),
            'pageSize' => 9,
            'params' => [
                'page' => $page,
            ],
            'pageSizeParam' => false,
            'validatePage' => true,
        ]);

        $buttons = [];

        $administrators = $query->offset($pagination->offset)
            ->limit($pagination->limit)
            ->all();

        if ($administrators) {
            foreach ($administrators as $administrator) {
                $administratorChatMember = $chat->getChatMemberByUser($administrator);

                $buttons[][] = [
                    'callback_data' => self::createRoute('set', [
                        'id' => $chat->id,
                        'administratorId' => $administrator->id,
                    ]),
                    'text' => ($administratorChatMember->status == ChatMember::STATUS_CREATOR ? Emoji::CROWN : ($administratorChatMember->role == ChatMember::ROLE_ADMINISTRATOR ? Emoji::STATUS_ON : Emoji::STATUS_OFF)) . ' ' . $administrator->getDisplayName(),
                ];
            }

            $paginationButtons = PaginationButtons::build($pagination, function ($page) use ($chat) {
                return self::createRoute('index', [
                    'id' => $chat->id,
                    'page' => $page,
                ]);
            });

            if ($paginationButtons) {
                $buttons[] = $paginationButtons;
            }
        }

        $buttons[] = [
            [
                'callback_data' => ChannelController::createRoute('view', [
                    'chatId' => $chat->id,
                ]),
                'text' => Emoji::BACK,
            ],
            [
                'callback_data' => MenuController::createRoute(),
                'text' => Emoji::MENU,
            ]
        ];

        return $this->getResponseBuilder()
            ->editMessageTextOrSendMessage(
                $this->render('index', [
                    'chat' => $chat,
                ]),
                $buttons
            )
            ->build();
    }

    // TODO remove this action and join it to 'administrators' action to display the current page correctly
    /**
     * @param int $chatId Chat->id
     * @param int $administratorId User->id
     * @return array
     */
    public function actionSet($id = null, $administratorId = null)
    {
        $chat = Yii::$app->cache->get('chat');
        $chatMember = Yii::$app->cache->get('chatMember');

        $this->getState()->clearInputRoute();
        // creator cannot be deactivated
        if ($chatMember->getUserId() == $administratorId) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        $administratorChatMember = ChatMember::findOne([
            'chat_id' => $chat->id,
            'user_id' => $administratorId,
        ]);

        if (!isset($administratorChatMember)) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        if ($administratorChatMember->isActiveAdministrator()) {
            $administratorChatMember->role = ChatMember::ROLE_MEMBER;
        } else {
            $administratorChatMember->role = ChatMember::ROLE_ADMINISTRATOR;
        }

        $administratorChatMember->save();

        return $this->runAction('index', [
             'id' => $chat->id,
         ]);
    }
}
