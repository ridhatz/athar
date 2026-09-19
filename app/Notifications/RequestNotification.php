<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\ServiceRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One notification about one request. Which channels carry it comes from config('athar.notifications.channels'),
 * so adding WhatsApp later means adding a channel name there and a class that can deliver it, not editing business logic.
 *
 * The stored payload keeps codes (status, priority) rather than translated text, so each reader sees
 * it in their own language.
 */
class RequestNotification extends Notification
{
    /**
     * @param  array<string, string|int|null>  $params  e.g. actor, from, to, due
     */
    public function __construct(
        public readonly NotificationType $type,
        public readonly ServiceRequest $request,
        public readonly array $params = [],
        public readonly ?string $dedupeKey = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return (array) config('athar.notifications.channels', ['database']);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type->value,
            'request_id' => $this->request->id,
            'request_number' => $this->request->request_number,
            'title' => $this->request->title,
            'params' => $this->params,
            'dedupe_key' => $this->dedupeKey,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->inLocale($notifiable->locale, function () {
            $message = $this->text();

            return (new MailMessage)
                ->subject("[{$this->request->request_number}] {$message}")
                ->line($message)
                ->line($this->request->title)
                ->action(__('notify.open'), route('requests.show', $this->request->request_number))
                ->line(__('notify.footer'));
        });
    }

    /** The sentence, in the current locale. */
    private function text(): string
    {
        $params = $this->params + ['number' => $this->request->request_number, 'title' => $this->request->title];

        // Status codes become the reader's words.
        foreach (['from', 'to'] as $key) {
            if (isset($params[$key])) {
                $params[$key] = __('status.'.$params[$key]);
            }
        }

        return __('notify.'.$this->type->value, $params);
    }

    /** @template T */
    private function inLocale(?string $locale, callable $callback): mixed
    {
        $previous = app()->getLocale();
        app()->setLocale(in_array($locale, ['id', 'en'], true) ? $locale : config('app.locale'));

        try {
            return $callback();
        } finally {
            app()->setLocale($previous);
        }
    }
}
