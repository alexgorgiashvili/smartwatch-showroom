@extends('admin.layout')

@section('title', 'სოციალური კომენტარები — Admin')

@section('content')
@fragment('content')
<div data-page-title="Social Comments">
    @include('admin.social-comments._content')
</div>
@endfragment
@endsection
