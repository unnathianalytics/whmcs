<x-mail::message>
{!! \Illuminate\Support\Str::markdown($bodyMarkdown) !!}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
