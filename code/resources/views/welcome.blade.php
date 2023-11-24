@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">{{ __('Change Log') }}</div>

                    <div class="card-body">
                        Version 0.1
                        <ul>
                            <li>Server command: LogChat</li>
                            <li>Server command: MemberCollection</li>
                            <li>Server command: Points</li>
                            <li>Server command: Poll</li>
                            <li>Server command: Rules</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
