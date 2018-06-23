@extends(config('mail-tracker.admin-template.name'))
@section(config('mail-tracker.admin-template.section'))
    <div class="container">
        <div class="row">
            <div class="col-sm-10">
                <h1>Mail Tracker</h1>
            </div>
            <div class="col-sm-2">
                <h1>
                    <a href="{{route('mailTracker_Index',['page'=>session('mail-tracker-index-page')])}}" class='btn btn-default'>All Sent Emails</a>
                </h1>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12 text-center">
                <h3>
                    SMTP detail for Email ID {{$details->id}}
                </h3>
                <a href="{{ route('mailTracker_ShowEmail',$details->id) }}" class="btn btn-default" target="_blank">
                    View Message
                </a>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12">
                Recipient: {{$details->recipient}} <br>
                Subject: {{$details->subject}} <br>
                Sent At: {{$details->created_at->format(config('mail-tracker.date-format'))}} <br>
                SMTP Details: {{ $details->smtp_info }}
            </div>
        </div>
    </div>
@endsection
