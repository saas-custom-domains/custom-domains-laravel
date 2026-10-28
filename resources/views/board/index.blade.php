@extends('layouts.app')

@section('title', "Jobs at {$board->name}")

@section('content')
    <h1>{{ $board->name }}</h1>
    <p class="muted">{{ $board->tagline }}</p>

    <h2>Open positions</h2>
    <ul>
        @foreach ($jobs as $job)
            <li><a href="{{ route('jobs.show', $job->id) }}">{{ $job->title }}</a> <span class="muted">{{ $job->location }}</span></li>
        @endforeach
    </ul>
@endsection
