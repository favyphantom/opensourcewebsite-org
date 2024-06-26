<?php

namespace app\modules\bot\controllers\privates;

use app\models\User as GlobalUser;
use app\modules\bot\components\Controller;
use app\modules\bot\components\helpers\Emoji;
use app\modules\bot\components\helpers\PaginationButtons;
use app\modules\bot\filters\GroupActiveAdministratorAccessFilter;
use app\modules\bot\models\Chat;
use app\modules\bot\models\ChatMember;
use app\modules\bot\models\ChatSetting;
use Yii;
use yii\data\Pagination;
use yii\validators\DateValidator;

/**
 * Class GroupMembershipController
 *
 * @package app\modules\bot\controllers\privates
 */
class GroupMembershipController extends Controller
{
    public function behaviors()
    {
        return [
            'groupActiveAdministratorAccess' => [
                'class' => GroupActiveAdministratorAccessFilter::class,
            ],
        ];
    }

    /**
     * @param int $id Chat->id
     * @return array
     */
    public function actionIndex($id = null)
    {
        $chat = Yii::$app->cache->get('chat');

        $this->getState()->clearInputRoute();

        return $this->getResponseBuilder()
            ->editMessageTextOrSendMessage(
                $this->render('index', [
                    'chat' => $chat,
                ]),
                [
                    [
                        [
                            'callback_data' => self::createRoute('set-status', [
                                'id' => $chat->id,
                            ]),
                            'text' => $chat->isMembershipOn() ? Emoji::STATUS_ON . ' ON' : Emoji::STATUS_OFF . ' OFF',
                        ],
                    ],
                    [
                        [
                            'callback_data' => self::createRoute('members', [
                                'id' => $chat->id,
                            ]),
                            'text' => Yii::t('bot', 'Members'),
                        ],
                    ],
                    [
                        [
                            'callback_data' => self::createRoute('set-tag', [
                                'id' => $chat->id,
                            ]),
                            'text' => Emoji::EDIT . ' ' . Yii::t('bot', 'Tag for members'),
                        ],
                    ],
                    [
                        [
                            'callback_data' => self::createRoute('child-groups', [
                                'id' => $chat->id,
                            ]),
                            'text' => Emoji::EDIT . ' ' . Yii::t('bot', 'Child groups'),
                        ],
                    ],
                    [
                        [
                            'callback_data' => GroupController::createRoute('view', [
                                'chatId' => $chat->id,
                            ]),
                            'text' => Emoji::BACK,
                        ],
                        [
                            'callback_data' => MenuController::createRoute(),
                            'text' => Emoji::MENU,
                        ],
                    ]
                ]
            )
            ->build();
    }

    /**
     * @param int $id Chat->id
     * @return array
     */
    public function actionSetStatus($id = null)
    {
        $chat = Yii::$app->cache->get('chat');
        $chatMember = Yii::$app->cache->get('chatMember');

        switch ($chat->membership_status) {
            case ChatSetting::STATUS_ON:
                $chat->membership_status = ChatSetting::STATUS_OFF;

                break;
            case ChatSetting::STATUS_OFF:
                if (!$chatMember->trySetChatSetting('membership_status', ChatSetting::STATUS_ON)) {
                    return $this->getResponseBuilder()
                        ->answerCallbackQuery(
                            $this->render('alert-status-on', [
                                'requiredRating' => $chatMember->getRequiredRatingForChatSetting('membership_status', ChatSetting::STATUS_ON),
                            ]),
                            true
                        )
                        ->build();
                }

                break;
        }

        return $this->actionIndex($chat->id);
    }

    /**
     * @param int $id Chat->id
     * @return array
     */
    public function actionSetTag($id = null)
    {
        $chat = Yii::$app->cache->get('chat');

        $this->getState()->setInputRoute(self::createRoute('input-tag', [
            'id' => $chat->id,
        ]));

        return $this->getResponseBuilder()
            ->editMessageTextOrSendMessage(
                $this->render('set-tag'),
                [
                    [
                        [
                            'callback_data' => self::createRoute('index', [
                                'id' => $chat->id,
                            ]),
                            'text' => Emoji::BACK,
                        ],
                    ],
                ]
            )
            ->build();
    }

    /**
     * @param int $id Chat->id
     * @return array
     */
    public function actionInputTag($id = null)
    {
        $chat = Yii::$app->cache->get('chat');

        if ($this->getUpdate()->getMessage()) {
            if ($text = $this->getUpdate()->getMessage()->getText()) {
                if ($chat->validateSettingValue('membership_tag', $text)) {
                    $chat->membership_tag = $text;

                    return $this->runAction('index', [
                        'id' => $chat->id,
                    ]);
                }
            }
        }

        return $this->getResponseBuilder()
        ->answerCallbackQuery()
        ->build();
    }

    /**
     * @param int $id Chat->id
     * @param int $page
     * @return array
     */
    public function actionMembers($id = null, $page = 1)
    {
        $chat = Yii::$app->cache->get('chat');

        $this->getState()->setInputRoute(self::createRoute('input-member', [
            'id' => $chat->id,
        ]));

        $query = $chat->getPremiumChatMembers()
            ->orderBy([
                'membership_date' => SORT_ASC,
                'user_id' => SORT_ASC,
            ]);

        $pagination = new Pagination([
            'totalCount' => $query->count(),
            'pageSize' => 9,
            'params' => [
                'page' => $page,
            ],
            'pageSizeParam' => false,
            'validatePage' => true,
        ]);

        $paginationButtons = PaginationButtons::build($pagination, function ($page) use ($chat) {
            return self::createRoute('members', [
                'id' => $chat->id,
                'page' => $page,
            ]);
        });

        $buttons = [];

        $members = $query->offset($pagination->offset)
            ->limit($pagination->limit)
            ->all();

        if ($members) {
            foreach ($members as $member) {
                $buttons[][] = [
                    'callback_data' => self::createRoute('member', [
                        'id' => $chat->id,
                        'oid' => $member->id,
                    ]),
                    'text' => $member->membership_date . ' - ' . $member->user->getDisplayName(),
                ];
            }

            if ($paginationButtons) {
                $buttons[] = $paginationButtons;
            }
        }

        $buttons[] = [
            [
                'callback_data' => self::createRoute('index', [
                    'id' => $chat->id,
                ]),
                'text' => Emoji::BACK,
            ],
            [
                'callback_data' => MenuController::createRoute(),
                'text' => Emoji::MENU,
            ],
        ];

        return $this->getResponseBuilder()
            ->editMessageTextOrSendMessage(
                $this->render('members', [
                    'chat' => $chat,
                ]),
                $buttons
            )
            ->build();
    }

    /**
     * @param int $id Chat->id
     * @return array
     */
    public function actionInputMember($id = null)
    {
        $chat = Yii::$app->cache->get('chat');

        if ($text = $this->getMessage()->getText()) {
            if (preg_match('/(?:^@(?:[A-Za-z0-9][_]{0,1})*[A-Za-z0-9]+)/i', $text, $matches)) {
                $username = ltrim($matches[0], '@');
            }
        }

        if (!isset($username)) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        $member = ChatMember::find()
            ->where([
                'chat_id' => $chat->id,
            ])
            ->joinWith('user')
            ->andWhere([
                '{{%bot_user}}.provider_user_name' => $username,
            ])
            ->one();

        if (!isset($member)) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        if (!$member->membership_date) {
            $member->membership_date = Yii::$app->formatter->asDate('tomorrow');
            $member->save(false);
        }

        return $this->runAction('member', [
            'id' => $chat->id,
            'oid' => $member->id,
        ]);
    }

    /**
     * @param int $id Chat->id
     * @param int $oid ChatMember->id
     * @return array
     */
    public function actionMember($id = null, $oid = null)
    {
        $chat = Yii::$app->cache->get('chat');

        $member = ChatMember::findOne([
            'id' => $oid,
            'chat_id' => $chat->id,
        ]);

        if (!isset($member)) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        $this->getState()->clearInputRoute();

        return $this->getResponseBuilder()
            ->editMessageTextOrSendMessage(
                $this->render('member', [
                    'chat' => $chat,
                    'chatMember' => $member,
                ]),
                [
                    [
                        [
                            'callback_data' => self::createRoute('set-member-note', [
                                'id' => $chat->id,
                                'oid' => $member->id,
                            ]),
                            'text' => Yii::t('bot', 'Note'),
                        ],
                    ],
                    [
                        [
                            'callback_data' => self::createRoute('set-member-tariff-price', [
                                'id' => $chat->id,
                                'oid' => $member->id,
                            ]),
                            'text' => Yii::t('bot', 'Tariff, price'),
                        ],
                        [
                            'callback_data' => self::createRoute('set-member-tariff-price-balance', [
                                'id' => $chat->id,
                                'oid' => $member->id,
                            ]),
                            'text' => Yii::t('bot', 'Balance, price'),
                        ],
                    ],
                    [
                        [
                            'callback_data' => self::createRoute('set-member-tariff-days', [
                                'id' => $chat->id,
                                'oid' => $member->id,
                            ]),
                            'text' => Yii::t('bot', 'Tariff, days'),
                        ],
                        [
                            'callback_data' => self::createRoute('set-member-tariff-days-balance', [
                                'id' => $chat->id,
                                'oid' => $member->id,
                            ]),
                            'text' => Yii::t('bot', 'Balance, days'),
                        ],
                    ],
                    [
                        [
                            'callback_data' => self::createRoute('set-member-membership-date', [
                                'id' => $chat->id,
                                'oid' => $member->id,
                            ]),
                            'text' => Yii::t('bot', 'Membership end date'),
                        ],
                    ],
                    [
                        [
                            'callback_data' => self::createRoute('set-member-verification-date', [
                                'id' => $chat->id,
                                'oid' => $member->id,
                            ]),
                            'text' => Yii::t('bot', 'Verification end date'),
                        ],
                    ],
                    [
                        [
                            'callback_data' => self::createRoute('members', [
                                'id' => $chat->id,
                            ]),
                            'text' => Emoji::BACK,
                        ],
                        [
                            'callback_data' => MenuController::createRoute(),
                            'text' => Emoji::MENU,
                        ],
                        [
                            'callback_data' => self::createRoute('delete-member-membership-date', [
                                'id' => $chat->id,
                                'oid' => $member->id,
                            ]),
                            'text' => Emoji::DELETE,
                        ],
                    ]
                ]
            )
            ->build();
    }

    /**
     * @param int $id Chat->id
     * @param int $oid ChatMember->id
     * @return array
     */
    public function actionSetMemberMembershipDate($id = null, $oid = null)
    {
        $chat = Yii::$app->cache->get('chat');

        $member = ChatMember::findOne([
            'id' => $oid,
            'chat_id' => $chat->id,
        ]);

        if (!isset($member)) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        $this->getState()->setInputRoute(self::createRoute('set-member-membership-date', [
            'id' => $chat->id,
            'oid' => $member->id,
        ]));

        if ($this->getUpdate()->getMessage()) {
            if ($text = $this->getUpdate()->getMessage()->getText()) {
                $dateValidator = new DateValidator();

                if ($dateValidator->validate($text)) {
                    $member->membership_date = Yii::$app->formatter->format($text, 'date');
                    $member->save(false);

                    return $this->runAction('member', [
                        'id' => $chat->id,
                        'oid' => $member->id,
                    ]);
                }
            }
        }

        return $this->getResponseBuilder()
            ->editMessageTextOrSendMessage(
                $this->render('set-member-membership-date'),
                [
                    [
                        [
                            'callback_data' => self::createRoute('member', [
                                'id' => $chat->id,
                                'oid' => $member->id,
                            ]),
                            'text' => Emoji::BACK,
                        ],
                    ],
                ]
            )
            ->build();
    }

    /**
     * @param int $id Chat->id
     * @param int $oid ChatMember->id
     * @return array
     */
    public function actionSetMemberVerificationDate($id = null, $oid = null)
    {
        $chat = Yii::$app->cache->get('chat');

        $member = ChatMember::findOne([
            'id' => $oid,
            'chat_id' => $chat->id,
        ]);

        if (!isset($member)) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        $this->getState()->setInputRoute(self::createRoute('set-member-verification-date', [
            'id' => $chat->id,
            'oid' => $member->id,
        ]));

        if ($this->getUpdate()->getMessage()) {
            if ($text = $this->getUpdate()->getMessage()->getText()) {
                $dateValidator = new DateValidator();

                if ($dateValidator->validate($text)) {
                    $member->limiter_date = Yii::$app->formatter->format($text, 'date');
                    $member->save(false);

                    return $this->runAction('member', [
                        'id' => $chat->id,
                        'oid' => $member->id,
                    ]);
                }
            }
        }

        return $this->getResponseBuilder()
            ->editMessageTextOrSendMessage(
                $this->render('set-member-verification-date'),
                [
                    [
                        [
                            'callback_data' => self::createRoute('member', [
                                'id' => $chat->id,
                                'oid' => $member->id,
                            ]),
                            'text' => Emoji::BACK,
                        ],
                        [
                            'callback_data' => self::createRoute('delete-member-verification-date', [
                                'id' => $chat->id,
                                'oid' => $member->id,
                            ]),
                            'text' => Emoji::DELETE,
                        ],
                    ],
                ]
            )
            ->build();
    }

    /**
     * @param int $id Chat->id
     * @param int $oid ChatMember->id
     * @return array
     */
    public function actionDeleteMemberMembershipDate($id = null, $oid = null)
    {
        $chat = Yii::$app->cache->get('chat');

        $member = ChatMember::findOne([
            'id' => $oid,
            'chat_id' => $chat->id,
        ]);

        if (!isset($member)) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        // remove membership
        $member->membership_date = null;
        $member->membership_tariff_price = null;
        $member->membership_tariff_days = null;
        // remove slow mode
        $member->slow_mode_messages_limit = null;
        $member->slow_mode_messages_skip_hours = null;

        $member->save(false);

        return $this->runAction('members', [
             'id' => $chat->id,
        ]);
    }

    /**
     * @param int $id Chat->id
     * @param int $oid ChatMember->id
     * @return array
     */
    public function actionDeleteMemberVerificationDate($id = null, $oid = null)
    {
        $chat = Yii::$app->cache->get('chat');

        $member = ChatMember::findOne([
            'id' => $oid,
            'chat_id' => $chat->id,
        ]);

        if (!isset($member)) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        $member->limiter_date = null;
        $member->save(false);

        return $this->runAction('member', [
            'id' => $chat->id,
            'oid' => $member->id,
        ]);
    }

    /**
     * @param int $id Chat->id
     * @param int $oid ChatMember->id
     * @return array
     */
    public function actionSetMemberNote($id = null, $oid = null)
    {
        $chat = Yii::$app->cache->get('chat');

        $member = ChatMember::findOne([
            'id' => $oid,
            'chat_id' => $chat->id,
        ]);

        if (!isset($member)) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        $this->getState()->setInputRoute(self::createRoute('set-member-note', [
            'id' => $chat->id,
            'oid' => $member->id,
        ]));

        if ($this->getUpdate()->getMessage()) {
            if ($text = $this->getUpdate()->getMessage()->getText()) {
                $member->membership_note = $text;

                if ($member->validate('membership_note')) {
                    $member->save(false);

                    return $this->runAction('member', [
                        'id' => $chat->id,
                        'oid' => $member->id,
                    ]);
                }
            }
        }

        return $this->getResponseBuilder()
            ->editMessageTextOrSendMessage(
                $this->render('set-member-note'),
                [
                    [
                        [
                            'callback_data' => self::createRoute('member', [
                                'id' => $chat->id,
                                'oid' => $member->id,
                            ]),
                            'text' => Emoji::BACK,
                        ],
                        [
                            'callback_data' => self::createRoute('delete-member-note', [
                                'id' => $chat->id,
                                'oid' => $member->id,
                            ]),
                            'text' => Emoji::DELETE,
                            'visible' => (bool)$member->getMembershipNote(),
                        ],
                    ],
                ]
            )
            ->build();
    }

    /**
     * @param int $id Chat->id
     * @param int $oid ChatMember->id
     * @return array
     */
    public function actionDeleteMemberNote($id = null, $oid = null)
    {
        $chat = Yii::$app->cache->get('chat');

        $member = ChatMember::findOne([
            'id' => $oid,
            'chat_id' => $chat->id,
        ]);

        if (!isset($member)) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        $member->membership_note = null;
        $member->save(false);

        return $this->runAction('member', [
            'id' => $chat->id,
            'oid' => $member->id,
        ]);
    }

    /**
     * @param int $id Chat->id
     * @param int $oid ChatMember->id
     * @return array
     */
    public function actionSetMemberTariffPrice($id = null, $oid = null)
    {
        $chat = Yii::$app->cache->get('chat');

        $member = ChatMember::findOne([
            'id' => $oid,
            'chat_id' => $chat->id,
        ]);

        if (!isset($member)) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        $this->getState()->setInputRoute(self::createRoute('set-member-tariff-price', [
            'id' => $chat->id,
            'oid' => $member->id,
        ]));

        if ($this->getUpdate()->getMessage()) {
            if ($text = $this->getUpdate()->getMessage()->getText()) {
                $member->membership_tariff_price = $text;

                if ($member->validate('membership_tariff_price')) {
                    $member->save(false);

                    return $this->runAction('member', [
                        'id' => $chat->id,
                        'oid' => $member->id,
                    ]);
                }
            }
        }

        return $this->getResponseBuilder()
            ->editMessageTextOrSendMessage(
                $this->render('../set-value'),
                [
                    [
                        [
                            'callback_data' => self::createRoute('member', [
                                'id' => $chat->id,
                                'oid' => $member->id,
                            ]),
                            'text' => Emoji::BACK,
                        ],
                        [
                            'callback_data' => self::createRoute('delete-member-tariff-price', [
                                'id' => $chat->id,
                                'oid' => $member->id,
                            ]),
                            'text' => Emoji::DELETE,
                            'visible' => (bool)$member->getMembershipTariffPrice(),
                        ],
                    ],
                ]
            )
            ->build();
    }

    /**
     * @param int $id Chat->id
     * @param int $oid ChatMember->id
     * @return array
     */
    public function actionDeleteMemberTariffPrice($id = null, $oid = null)
    {
        $chat = Yii::$app->cache->get('chat');

        $member = ChatMember::findOne([
            'id' => $oid,
            'chat_id' => $chat->id,
        ]);

        if (!isset($member)) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        $member->membership_tariff_price = null;
        $member->save(false);

        return $this->runAction('member', [
            'id' => $chat->id,
            'oid' => $member->id,
        ]);
    }

    /**
     * @param int $id Chat->id
     * @param int $oid ChatMember->id
     * @return array
     */
    public function actionSetMemberTariffPriceBalance($id = null, $oid = null)
    {
        $chat = Yii::$app->cache->get('chat');

        $member = ChatMember::findOne([
            'id' => $oid,
            'chat_id' => $chat->id,
        ]);

        if (!isset($member)) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        $this->getState()->setInputRoute(self::createRoute('set-member-tariff-price-balance', [
            'id' => $chat->id,
            'oid' => $member->id,
        ]));

        if ($this->getUpdate()->getMessage()) {
            if (($text = $this->getUpdate()->getMessage()->getText()) !== null) {
                if ($member->setMembershipTariffPriceBalance($text)) {
                    $member->save(false);

                    return $this->runAction('member', [
                        'id' => $chat->id,
                        'oid' => $member->id,
                    ]);
                }
            }
        }

        return $this->getResponseBuilder()
            ->editMessageTextOrSendMessage(
                $this->render('../set-value'),
                [
                    [
                        [
                            'callback_data' => self::createRoute('member', [
                                'id' => $chat->id,
                                'oid' => $member->id,
                            ]),
                            'text' => Emoji::BACK,
                        ],
                    ],
                ]
            )
            ->build();
    }

    /**
     * @param int $id Chat->id
     * @param int $oid ChatMember->id
     * @return array
     */
    public function actionSetMemberTariffDays($id = null, $oid = null)
    {
        $chat = Yii::$app->cache->get('chat');

        $member = ChatMember::findOne([
            'id' => $oid,
            'chat_id' => $chat->id,
        ]);

        if (!isset($member)) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        $this->getState()->setInputRoute(self::createRoute('set-member-tariff-days', [
            'id' => $chat->id,
            'oid' => $member->id,
        ]));

        if ($this->getUpdate()->getMessage()) {
            if ($text = $this->getUpdate()->getMessage()->getText()) {
                $member->membership_tariff_days = $text;

                if ($member->validate('membership_tariff_days')) {
                    $member->save(false);

                    return $this->runAction('member', [
                        'id' => $chat->id,
                        'oid' => $member->id,
                    ]);
                }
            }
        }

        return $this->getResponseBuilder()
            ->editMessageTextOrSendMessage(
                $this->render('../set-value'),
                [
                    [
                        [
                            'callback_data' => self::createRoute('member', [
                                'id' => $chat->id,
                                'oid' => $member->id,
                            ]),
                            'text' => Emoji::BACK,
                        ],
                        [
                            'callback_data' => self::createRoute('delete-member-tariff-days', [
                                'id' => $chat->id,
                                'oid' => $member->id,
                            ]),
                            'text' => Emoji::DELETE,
                            'visible' => (bool)$member->getMembershipTariffDays(),
                        ],
                    ],
                ]
            )
            ->build();
    }

    /**
     * @param int $id Chat->id
     * @param int $oid ChatMember->id
     * @return array
     */
    public function actionDeleteMemberTariffDays($id = null, $oid = null)
    {
        $chat = Yii::$app->cache->get('chat');

        $member = ChatMember::findOne([
            'id' => $oid,
            'chat_id' => $chat->id,
        ]);

        if (!isset($member)) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        $member->membership_tariff_days = null;
        $member->save(false);

        return $this->runAction('member', [
            'id' => $chat->id,
            'oid' => $member->id,
        ]);
    }

    /**
     * @param int $id Chat->id
     * @param int $oid ChatMember->id
     * @return array
     */
    public function actionSetMemberTariffDaysBalance($id = null, $oid = null)
    {
        $chat = Yii::$app->cache->get('chat');

        $member = ChatMember::findOne([
            'id' => $oid,
            'chat_id' => $chat->id,
        ]);

        if (!isset($member)) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        $this->getState()->setInputRoute(self::createRoute('set-member-tariff-days-balance', [
            'id' => $chat->id,
            'oid' => $member->id,
        ]));

        if ($this->getUpdate()->getMessage()) {
            if (($text = $this->getUpdate()->getMessage()->getText()) !== null) {
                if ($member->setMembershipTariffDaysBalance($text)) {
                    $member->save(false);

                    return $this->runAction('member', [
                        'id' => $chat->id,
                        'oid' => $member->id,
                    ]);
                }
            }
        }

        return $this->getResponseBuilder()
            ->editMessageTextOrSendMessage(
                $this->render('../set-value'),
                [
                    [
                        [
                            'callback_data' => self::createRoute('member', [
                                'id' => $chat->id,
                                'oid' => $member->id,
                            ]),
                            'text' => Emoji::BACK,
                        ],
                    ],
                ]
            )
            ->build();
    }

    /**
     * @param int|null $id Chat->id
     * @param int $page
     * @return array
     */
    public function actionChildGroups($id = null, $page = 1)
    {
        $chat = Yii::$app->cache->get('chat');

        $this->getState()->clearInputRoute();

        $query = $this->getTelegramUser()
            ->getActiveAdministratedGroups()
            ->andWhere([
                '!=', 'id', $chat->id,
            ]);
        ;

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

        $childChats = $query->offset($pagination->offset)
            ->limit($pagination->limit)
            ->all();

        if ($childChats) {
            foreach ($childChats as $childChat) {
                $buttons[][] = [
                    'callback_data' => self::createRoute('set-child-group', [
                        'id' => $chat->id,
                        'cid' => $childChat->id,
                    ]),
                    'text' => ($chat->hasChildGroupById($childChat->id) ? Emoji::STATUS_ON . ' ' : '') . $childChat->title,
                ];
            }

            $paginationButtons = PaginationButtons::build($pagination, function ($page) use ($chat) {
                return self::createRoute('child-groups', [
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
                'callback_data' => self::createRoute('index', [
                    'id' => $chat->id,
                ]),
                'text' => Emoji::BACK,
            ],
            [
                'callback_data' => self::createRoute('sync-child-groups', [
                    'id' => $chat->id,
                ]),
                'text' => Emoji::SYNC,
            ],
            [
                'callback_data' => MenuController::createRoute(),
                'text' => Emoji::MENU,
            ],
        ];

        return $this->getResponseBuilder()
            ->editMessageTextOrSendMessage(
                $this->render('child-groups', [
                    'chat' => $chat,
                ]),
                $buttons
            )
            ->build();
    }

    /**
     * @param int $id Chat->id
     * @param int $cid Chat->id
     * @return array
     */
    public function actionSetChildGroup($id = null, $cid = null)
    {
        $chat = Yii::$app->cache->get('chat');

        $childChat = Chat::findOne($cid);

        if (!isset($childChat)) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        $childChatMember = $childChat->getChatMemberByUserId();

        if (!((isset($childChatMember) && $childChatMember->isActiveAdministrator()) || $chat->hasChildGroupById($childChat->id))) {
            return $this->getResponseBuilder()
                ->answerCallbackQuery()
                ->build();
        }

        if ($chat->hasChildGroupById($childChat->id)) {
            $chat->unlink('childGroups', $childChat, true);
        } else {
            $user = $this->getTelegramUser();

            $chat->link('childGroups', $childChat, [
                'updated_by' => $user->id,
            ]);
        }

        return $this->runAction('child-groups', [
             'id' => $chat->id,
         ]);
    }

    /**
     * @param int|null $id Chat->id
     * @return array
     */
    public function actionSyncChildGroups($id = null)
    {
        $chat = Yii::$app->cache->get('chat');

        if (!$premiumChatMembers = $chat->premiumChatMembers) {
            return $this->getResponseBuilder()
            ->answerCallbackQuery(
                $this->render('alert-no-premium-members')
            )
            ->build();
        }

        if (!$childChats = $chat->childGroups) {
            return $this->getResponseBuilder()
            ->answerCallbackQuery(
                $this->render('alert-no-child-groups')
            )
            ->build();
        }

        $updatedCount = 0;

        foreach ($childChats as $childChat) {
            foreach ($premiumChatMembers as $premiumChatMember) {
                if ($childChatMember = $childChat->getChatMemberByUserId($premiumChatMember->getUserId())) {
                    $childChatMember->membership_date = $premiumChatMember->membership_date;
                    $childChatMember->limiter_date = $premiumChatMember->limiter_date;
                    $childChatMember->save();

                    $updatedCount++;
                }
            }
        }

        return $this->getResponseBuilder()
        ->answerCallbackQuery(
            $this->render('alert-sync-child-groups', [
                'updatedCount' => $updatedCount,
                ])
        )
        ->build();
    }
}
