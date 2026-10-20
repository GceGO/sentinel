<x-gcego-sentinel::page :context="$context">
    <h1 id="sentinel-heading">Custom sign-in for {{ $context->branding->name }}</h1>
    <form method="POST" action="{{ $context->url('login.store') }}">@csrf</form>
</x-gcego-sentinel::page>
