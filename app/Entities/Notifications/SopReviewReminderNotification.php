<?php

namespace BookStack\Entities\Notifications;

use BookStack\Activity\Notifications\MessageParts\EntityLinkMessageLine;
use BookStack\Activity\Notifications\MessageParts\ListMessageLine;
use BookStack\App\MailNotification;
use BookStack\Entities\Models\Page;
use BookStack\Users\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Notification sent to SOP owners when their SOPs are due for review.
 */
class SopReviewReminderNotification extends MailNotification
{
    public function __construct(
        protected Page $page,
        protected int $daysOverdue = 0,
    ) {
    }

    public function toMail(User $notifiable): MailMessage
    {
        $locale = $notifiable->getLocale();
        $appName = setting('app-name');

        $listLines = [
            $locale->trans('notifications.detail_sop_name') => new EntityLinkMessageLine($this->page),
            $locale->trans('notifications.detail_review_due') => $this->page->next_review_date->format('M j, Y'),
        ];

        if ($this->daysOverdue > 0) {
            $listLines[$locale->trans('notifications.detail_days_overdue')] = (string) $this->daysOverdue;
        }

        $subject = $this->daysOverdue > 0
            ? $locale->trans('notifications.sop_review_overdue_subject', ['sopName' => $this->page->getShortName()])
            : $locale->trans('notifications.sop_review_due_subject', ['sopName' => $this->page->getShortName()]);

        $intro = $this->daysOverdue > 0
            ? $locale->trans('notifications.sop_review_overdue_intro', ['appName' => $appName])
            : $locale->trans('notifications.sop_review_due_intro', ['appName' => $appName]);

        return $this->newMailMessage($locale)
            ->subject($subject)
            ->line($intro)
            ->line(new ListMessageLine($listLines))
            ->action($locale->trans('notifications.action_view_sop'), $this->page->getUrl())
            ->line($locale->trans('notifications.sop_review_footer'));
    }
}

