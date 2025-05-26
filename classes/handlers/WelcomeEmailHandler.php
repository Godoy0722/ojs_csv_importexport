<?php

/**
 * @file plugins/importexport/csv/classes/handlers/WelcomeEmailHandler.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WelcomeEmailHandler
 *
 * @ingroup plugins_importexport_csv
 *
 * @brief Handles the welcome email when the user uses the user command
 */

namespace APP\plugins\importexport\csv\classes\handlers;

use APP\journal\Journal;
use APP\notification\Notification;
use APP\notification\NotificationManager;
use Illuminate\Support\Facades\Mail;
use PKP\facades\Repo;
use PKP\mail\mailables\UserCreated;
use PKP\user\User;
use Symfony\Component\Mailer\Exception\TransportException;

class WelcomeEmailHandler
{

    public static function sendWelcomeEmail(Journal $context, User $recipient, User $sender, string $password)
    {
        $mailable = new UserCreated($context, $password);
        $mailable->recipients($recipient);
        $mailable->sender($sender);
        $mailable->replyTo($context->getData('contactEmail'), $context->getData('contactName'));
        $template = Repo::emailTemplate()->getByKey($context->getId(), UserCreated::getEmailTemplateKey());
        $mailable->body($template->getLocalizedData('body'));
        $mailable->subject($template->getLocalizedData('subject'));

        try {
            Mail::send($mailable);
        } catch (TransportException $e) {
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification(
                $sender->getId(),
                Notification::NOTIFICATION_TYPE_ERROR,
                ['contents' => __('email.compose.error')]
            );
            error_log($e->getMessage());
        }
    }
}
