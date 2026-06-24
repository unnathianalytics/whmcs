<?php

namespace App\Support;

use App\Models\ClientService;
use App\Models\Domain;

/**
 * What: Substitutes the reminder template variables ({client_name}, {product_name}/{domain_name},
 *       {expires_at}, {days_left}) in a rule's subject/body for a specific expiring resource.
 * Why: The same placeholder set is needed by the queued Mailable, by the "send now" preview, and by the
 *      tests; centralising the resolution keeps one definition of what each token means and how a service
 *      vs. a domain maps onto {product_name}/{domain_name}. Unknown tokens are left untouched.
 * When: Called by the ReminderDispatcher just before building the Mailable for each send.
 */
class ReminderTemplate
{
    /**
     * What: Render a template string against the resource's reminder variables.
     * Why: A single `strtr` over a resolved token map keeps substitution predictable and order-independent.
     * When: Used for both the subject and the body of every reminder email.
     */
    public static function render(string $template, ClientService|Domain $remindable): string
    {
        return strtr($template, self::variables($remindable));
    }

    /**
     * What: Build the token → value map for an expiring resource.
     * Why: Services expose {product_name} (the service label or its product name); domains expose
     *      {domain_name}. Both share {client_name}, {expires_at} and {days_left}. The non-applicable name
     *      token is still defined (as the applicable name) so a mismatched template never leaks a raw token.
     * When: Called by `render()` for each template string.
     *
     * @return array<string, string>
     */
    public static function variables(ClientService|Domain $remindable): array
    {
        $expiresAt = $remindable->expires_at;
        $daysLeft = $remindable->daysUntilExpiry();

        if ($remindable instanceof Domain) {
            $name = $remindable->domain_name;
        } else {
            $productName = $remindable->product_id !== null ? $remindable->product->name : null;
            $name = $remindable->label ?: ($productName ?? __('your service'));
        }

        return [
            '{client_name}' => $remindable->client->name,
            '{product_name}' => $name,
            '{domain_name}' => $name,
            '{expires_at}' => $expiresAt?->format('M j, Y') ?? '',
            '{days_left}' => (string) ($daysLeft ?? 0),
        ];
    }
}
