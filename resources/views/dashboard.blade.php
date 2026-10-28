@extends('layouts.app')

@section('title', 'Hireboard dashboard')

@section('content')
    <h1>Hireboard dashboard</h1>
    <p class="muted">Connect a custom domain to each company's job board.</p>

    @foreach ($boards as $board)
        @php($errorBag = $errors->getBag("board{$board->id}"))
        <section data-testid="board-{{ $board->slug }}">
            <h2>{{ $board->name }}</h2>

            <ul class="domains">
                @foreach ($board->customDomains as $domain)
                    <li data-testid="domain-{{ $domain->host }}">
                        <code>{{ $domain->host }}</code>
                        <span @class(['badge', 'ok' => $domain->status === 'active'])>{{ $domain->statusLabel() }}</span>
                        <form method="POST" action="{{ route('domains.check', $domain) }}" style="margin-left:auto">
                            @csrf
                            <button class="link">Check DNS</button>
                        </form>
                        <form method="POST" action="{{ route('domains.destroy', $domain) }}">
                            @csrf
                            @method('DELETE')
                            <button class="link danger">Remove</button>
                        </form>
                    </li>
                @endforeach
            </ul>

            <form method="POST" action="{{ route('domains.store', $board) }}" class="row">
                @csrf
                <label>Domain <input name="host" placeholder="jobs.{{ $board->slug }}.com" required></label>
                <label>DNS contact <input name="dns_contact" type="email" placeholder="it@{{ $board->slug }}.com"></label>
                <button>Add domain</button>
            </form>
            @if ($errorBag->has('host'))
                <p class="error" role="alert">{{ $errorBag->first('host') }}</p>
            @endif
        </section>
    @endforeach
@endsection
