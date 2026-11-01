<?php

declare(strict_types=1);

namespace App\Notifier;

/**
 * What a chat message is about; each feed has its own Slack channel and Telegram chat.
 */
enum ChatFeed
{
    /** New users, stats uploads, CSV imports (ChatActivity) */
    case Activity;

    /** Errors and deprecations (App\Log\ChatLogHandler) */
    case Errors;
}
