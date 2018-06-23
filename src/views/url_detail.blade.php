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
                    Clicked URLs for Email ID {{$details->first()->email->id}}
                </h3>
                <a href="{{ route('mailTracker_ShowEmail',$details->first()->email->id) }}" class="btn btn-default" target="_blank">
                    View Message
                </a>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12">
                Recipient: {{$details->first()->email->recipient}} <br>
                Subject: {{$details->first()->email->subject}} <br>
                Sent At: {{$details->first()->email->created_at->format(config('mail-tracker.date-format'))}}
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12">
                <table class="table table-striped">
                  <th>Url</th>
                  <th>Clicks</th>
                  <th>First Click At</th>
                  <th>Last Click At</th>
                  @foreach($details as $detail)
                      <tr>
                          <td>{{$detail->url}}</td>
                          <td>{{$detail->clicks}}</td>
                          <td>{{$detail->created_at->format(config('mail-tracker.date-format'))}}</td>
                          <td>{{$detail->updated_at->format(config('mail-tracker.date-format'))}}</td>
                      </tr>
                  @endforeach
                </table>
            </div>
        </div>
    </div>
@endsection
