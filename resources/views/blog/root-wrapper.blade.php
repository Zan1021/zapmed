@php
    $post = \App\Models\BlogPost::published()->where('slug', $slug)->with('category')->firstOrFail();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $post->meta_title_tag }}</title>

        @include('partials.seo-meta', [
            'metaTitle'       => $post->meta_title_tag,
            'metaDescription' => $post->meta_description_tag,
            'canonical'       => $post->url,
            'ogImage'         => $post->og_image_url,
            'ogType'          => 'article',
        ])

        @include('partials.fonts')

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        @livewire('blog.blog-show', ['slug' => $slug])
    </body>
</html>
