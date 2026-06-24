<div class="flex h-full w-full flex-1 flex-col gap-6">
    <flux:heading size="xl">{{ __('Settings') }}</flux:heading>

    <flux:tabs wire:model.live="tab">
        <flux:tab name="company" icon="building-office-2">{{ __('Company') }}</flux:tab>
        <flux:tab name="billing" icon="banknotes">{{ __('Billing') }}</flux:tab>
        <flux:tab name="email" icon="envelope">{{ __('Email') }}</flux:tab>
        <flux:tab name="localisation" icon="globe-alt">{{ __('Localisation') }}</flux:tab>
        <flux:tab name="gateways" icon="credit-card">{{ __('Gateways') }}</flux:tab>
        <flux:tab name="reminders" icon="bell-alert">{{ __('Reminders') }}</flux:tab>
    </flux:tabs>

    @php($canManage = auth()->user()->can('settings.manage'))

    {{-- Company --}}
    @if ($tab === 'company')
        <flux:card class="max-w-2xl">
            <form wire:submit="saveCompany" class="flex flex-col gap-4">
                <flux:input wire:model="company_name" :label="__('Company name')" required />
                <flux:input wire:model="company_email" type="email" :label="__('Email')" />
                <flux:input wire:model="company_phone" :label="__('Phone')" />
                <flux:textarea wire:model="company_address" :label="__('Address')" rows="3" />
                @if ($canManage)
                    <div><flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button></div>
                @endif
            </form>
        </flux:card>
    @endif

    {{-- Billing --}}
    @if ($tab === 'billing')
        <flux:card class="max-w-2xl">
            <form wire:submit="saveBilling" class="flex flex-col gap-4">
                <flux:input wire:model="currency" :label="__('Default currency (ISO 4217)')" maxlength="3" required />
                <flux:input wire:model="tax_label" :label="__('Tax label')" required />
                <flux:input wire:model="invoice_prefix" :label="__('Invoice prefix')" required />
                <flux:input wire:model="invoice_due_days" type="number" :label="__('Invoice due days')" required />
                @if ($canManage)
                    <div><flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button></div>
                @endif
            </form>
        </flux:card>
    @endif

    {{-- Email --}}
    @if ($tab === 'email')
        <flux:card class="max-w-2xl">
            <form wire:submit="saveEmail" class="flex flex-col gap-4">
                <flux:input wire:model="mail_from_name" :label="__('From name')" />
                <flux:input wire:model="mail_from_email" type="email" :label="__('From email')" />
                <flux:input wire:model="smtp_host" :label="__('SMTP host')" />
                <flux:input wire:model="smtp_port" type="number" :label="__('SMTP port')" required />
                <flux:input wire:model="smtp_username" :label="__('SMTP username')" />
                <flux:input wire:model="smtp_password" type="password" :label="__('SMTP password')"
                    :placeholder="__('Leave blank to keep current')" />
                <flux:select wire:model="smtp_encryption" :label="__('Encryption')">
                    <flux:select.option value="tls">TLS</flux:select.option>
                    <flux:select.option value="ssl">SSL</flux:select.option>
                    <flux:select.option value="none">{{ __('None') }}</flux:select.option>
                </flux:select>
                @if ($canManage)
                    <div><flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button></div>
                @endif
            </form>
        </flux:card>
    @endif

    {{-- Localisation --}}
    @if ($tab === 'localisation')
        <flux:card class="max-w-2xl">
            <form wire:submit="saveLocalisation" class="flex flex-col gap-4">
                <flux:input wire:model="date_format" :label="__('Date format')" :description="__('PHP date() format, e.g. Y-m-d')" required />
                <flux:input wire:model="timezone" :label="__('Timezone')" :description="__('e.g. UTC, America/New_York')" required />
                <flux:input wire:model="default_language" :label="__('Default language')" maxlength="10" required />
                @if ($canManage)
                    <div><flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button></div>
                @endif
            </form>
        </flux:card>
    @endif

    {{-- Gateways --}}
    @if ($tab === 'gateways')
        <flux:card class="max-w-2xl">
            <form wire:submit="saveGateways" class="flex flex-col gap-6">
                <div class="flex flex-col gap-4">
                    <flux:switch wire:model="stripe_enabled" :label="__('Stripe enabled')" />
                    <flux:input wire:model="stripe_key" :label="__('Stripe publishable key')" />
                    <flux:input wire:model="stripe_secret" type="password" :label="__('Stripe secret key')"
                        :placeholder="__('Leave blank to keep current')" />
                </div>
                <flux:separator />
                <div class="flex flex-col gap-4">
                    <flux:switch wire:model="paypal_enabled" :label="__('PayPal enabled')" />
                    <flux:input wire:model="paypal_client_id" :label="__('PayPal client ID')" />
                    <flux:input wire:model="paypal_secret" type="password" :label="__('PayPal secret')"
                        :placeholder="__('Leave blank to keep current')" />
                </div>
                @if ($canManage)
                    <div><flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button></div>
                @endif
            </form>
        </flux:card>
    @endif

    {{-- Reminders --}}
    @if ($tab === 'reminders')
        <flux:card class="max-w-2xl">
            <form wire:submit="saveReminders" class="flex flex-col gap-4">
                <flux:switch wire:model="reminders_enabled" :label="__('Expiry reminders enabled')"
                    :description="__('Master switch for the daily reminders:send job.')" />

                <flux:field>
                    <flux:label>{{ __('Default lead times (days before expiry)') }}</flux:label>
                    <flux:description>{{ __('Comma-separated, e.g. 30, 14, 7, 1. Used as defaults when creating reminder rules.') }}</flux:description>
                    <div class="flex flex-wrap gap-2 pt-1">
                        @forelse ($reminder_lead_times as $i => $days)
                            <flux:badge color="blue">{{ $days }} {{ __('days') }}</flux:badge>
                        @empty
                            <flux:text class="text-zinc-400">{{ __('None set') }}</flux:text>
                        @endforelse
                    </div>
                </flux:field>

                <flux:input
                    type="text"
                    :value="implode(', ', $reminder_lead_times)"
                    wire:change="$set('reminder_lead_times', $event.target.value.split(',').map(s => parseInt(s.trim())).filter(n => !isNaN(n)))"
                    :label="__('Edit lead times')"
                    :placeholder="__('30, 14, 7, 1')" />
                <flux:error name="reminder_lead_times" />
                <flux:error name="reminder_lead_times.*" />

                @if ($canManage)
                    <div><flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button></div>
                @endif
            </form>
        </flux:card>
    @endif
</div>
