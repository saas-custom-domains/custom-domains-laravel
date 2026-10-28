@extends('layouts.app')

@section('title', "{$job->title} at {$board->name}")

@section('content')
    <p><a href="{{ route('board') }}">{{ $board->name }}</a></p>
    <h1>{{ $job->title }}</h1>
    <p class="muted">{{ $job->location }}</p>
    <p>{{ $job->description }}</p>

    @if (session('status'))
        <p class="notice" role="status">{{ session('status') }}</p>
    @endif

    <section>
        <h2>Apply</h2>
        <form method="POST" action="{{ route('jobs.apply', $job->id) }}" class="row">
            @csrf
            <label>Name <input name="name" value="{{ old('name') }}" required></label>
            <label>Email <input name="email" type="email" value="{{ old('email') }}" required></label>
            <button>Send application</button>
        </form>
        @error('email')<p class="error" role="alert">{{ $message }}</p>@enderror
        @error('name')<p class="error" role="alert">{{ $message }}</p>@enderror
    </section>
@endsection
