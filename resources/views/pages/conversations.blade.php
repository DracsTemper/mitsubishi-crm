@extends('layouts.app') @section('title','Conversations') @section('content')
<div class="panel conversation-shell">
    <aside class="conversation-list">
        <div class="p-3 border-bottom"><input class="form-control" placeholder="Search conversations"></div>@foreach([['Rahim Ahmed','Interested in Outlander 2.4','RA'],['Nusrat Jahan','Test drive availability','NJ'],['Sabbir Hasan','Booking payment receipt','SH'],['Tanvir Rahman','Xforce colour options','TR']] as $i=>$c)<div class="conversation-contact {{ $i===0?'active':'' }}" data-chat-contact data-name="{{ $c[0] }}"><span class="avatar">{{ $c[2] }}</span>
            <div><strong>{{ $c[0] }}</strong><small class="d-block text-secondary">{{ $c[1] }}</small></div>
        </div>@endforeach
    </aside>
    <section class="chat-pane">
        <div class="chat-header d-flex justify-content-between">
            <div><strong id="chatName">Rahim Ahmed</strong><small class="d-block text-secondary">Outlander 2.4 AWD · Mitsubishi Uttara</small></div><x-status status="Reserved" />
        </div>
        <div class="messages">
            <div class="text-center text-secondary small mb-4">Today, 25 August 2026</div>
            <div class="message">Hello, is the Outlander available for a test drive?<small>Rahim · 9:42 AM</small></div>
            <div class="message out">Yes. We have availability tomorrow at 10:30 AM.<small>John Doe · 9:47 AM</small></div>
            <div class="message">Perfect. Please reserve that slot for me.<small>Rahim · 9:49 AM</small></div>
            <div class="message out">Confirmed. Your test drive is scheduled at Mitsubishi Uttara.<small>John Doe · 9:51 AM</small></div>
        </div>
        <div class="composer"><input class="form-control" id="messageInput" placeholder="Type a message..."><button class="btn btn-primary" id="sendMessage">Send</button></div>
    </section>
</div>
@endsection