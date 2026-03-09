@extends('layouts.storefront')

@section('title', $page->title)

@section('content')
    <article>
        <h1 class="text-2xl font-bold mb-6">{{ $page->title }}</h1>
        <div class="prose max-w-none">{!! $page->body !!}</div>
    </article>
@endsection
