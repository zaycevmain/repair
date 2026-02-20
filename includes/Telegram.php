<?php
namespace Repair;

/**
 * Отправка уведомлений в Telegram (бот в группу/канал).
 * Настройки: telegram_bot_token, telegram_chat_id в группе "telegram".
 */
class Telegram {
    /** Отправить сообщение. useHtml=true — форматирование <b>, <i>, <code>. */
    public static function send(string $text, bool $useHtml = false): bool {
        $token = trim(setting('telegram', 'telegram_bot_token') ?? '');
        $chatId = trim(setting('telegram', 'telegram_chat_id') ?? '');
        if ($token === '' || $chatId === '') {
            return false;
        }
        $url = 'https://api.telegram.org/bot' . $token . '/sendMessage';
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'disable_web_page_preview' => true,
        ];
        if ($useHtml) {
            $payload['parse_mode'] = 'HTML';
        }
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($payload),
                'timeout' => 10,
            ],
        ]);
        $response = @file_get_contents($url, false, $ctx);
        if ($response === false) {
            return false;
        }
        $data = @json_decode($response, true);
        return !empty($data['ok']);
    }

    private static $defaults = [
        'new_breakdown' => "🔔 <b>Новая поломка</b>\n<b>Объект:</b> {object}\n<b>Инв. номер:</b> {inventory_number}\n<b>Место:</b> {place}\n<b>Кто:</b> {reporter}\n<b>Описание:</b> {description}\n<b>Как воспроизвести:</b> {reproduction}\n{date}",
        'repair_done' => "✅ <b>Выполнен ремонт</b>\nПоломка #{id}\n<b>Объект:</b> {object}\n<b>Инв:</b> {inventory_number}\n<b>Что сделано:</b> {completion_notes}",
        'closed_no_repair' => "📋 <b>Закрыто без ремонта</b>\nПоломка #{id}\n<b>Объект:</b> {object}\n<b>Инв:</b> {inventory_number}\n<b>Действие:</b> {closed_action}",
        'reopened' => "🔄 <b>Повторно открыта поломка</b>\nПоломка #{id}\n<b>Объект:</b> {object}\n<b>Инв:</b> {inventory_number}\nДата заявки: {reported_at}",
    ];

    /** Отправить по шаблону. Если шаблон в настройках пуст — используется стандартный. $vars — [ '{key}' => value ]. */
    public static function sendTemplate(string $key, array $vars): bool {
        $tpl = trim(setting('telegram_tpl', $key) ?? '');
        if ($tpl === '' && isset(self::$defaults[$key])) {
            $tpl = self::$defaults[$key];
        }
        if ($tpl === '') {
            return false;
        }
        $escaped = array_map(function ($v) {
            return htmlspecialchars((string) $v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }, $vars);
        $text = str_replace(array_keys($escaped), array_values($escaped), $tpl);
        return self::send($text, true);
    }
}
