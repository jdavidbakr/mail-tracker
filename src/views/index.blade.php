@extends(config('mail-tracker.admin-template.name'))
@section(config('mail-tracker.admin-template.section'))
    <div class="container">
        <div class="row">
            <div class="col-sm-12">
                <h1>Mail Tracker</h1>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12">
                <h4 class="text-center">
                    SNS Endpoint: {{ route('mailTracker_SNS') }}
                </h4>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12 text-center">
                <form action="{{ route('mailTracker_Search') }}" method="post">
                    {!! csrf_field() !!}
                    <div class="col-sm-{{ !is_null(config('mail-tracker.search-date-start')) ? '3' : '4' }}">
                        <div class="form-group">
                            <label for="search" class="form-label">
                                Search
                            </label>
                            <input type="text" class="form-control" name="search" id="search" value="{{ session('mail-tracker-index-search') }}">
                        </div>
                    </div>
                    @if( !is_null(config('mail-tracker.search-date-start')) )
                        <div class="col-sm-3">
                            <div class="form-group">
                                <label for="date_start" class="form-label">
                                    Sent at - beginning
                                </label>
                                <input type="date" class="form-control" name="date_start" id="date_start" value="{{ session('mail-tracker-index-date-start') }}">
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-group">
                                <label for="date_end" class="form-label">
                                    Sent at - end
                                </label>
                                <input type="date" class="form-control" name="date_end" id="date_end" value="{{ session('mail-tracker-index-date-end') }}">
                            </div>
                        </div>
                    @endif
                    <div class="col-sm-{{ !is_null(config('mail-tracker.search-date-start')) ? '3' : '8' }}">
                        <button type="submit" class="btn btn-default">
                            Search
                        </button>
                        <div class="btn btn-default">
                            <a href="{{ route('mailTracker_ClearSearch') }}">
                                Clear Search
                            </a>
                        </div>
                    </div>
                </form>
                <hr>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12">
                <table class="table table-striped">
                    <tr>
                        <th>SMTP</th>
                        <th>Recipient</th>
                        <th>Subject</th>
                        <th>First View</th>
                        <th>Opens</th>
                        <th>First Click</th>
                        <th>Clicks</th>
                        <th>Sent At</th>
                        <th>View Email</th>
                        <th>Click Report</th>
                    </tr>
                @foreach($emails as $email)
                    <tr class="{{ $email->report_class }}">
                      <td>
                        <a href="{{route('mailTracker_SmtpDetail',$email->id)}}" target="_blank">
                          {{ Str::limit($email->smtp_info, 20) }}
                        </a>
                      </td>
                      <td>{{$email->recipient}}</td>
                      <td>{{$email->subject}}</td>
                      <td>{{$email->opened_at}}</td>
                      <td>{{$email->opens}}</td>
                      <td>{{$email->clicked_at}}</td>
                      <td>{{$email->clicks}}</td>
                      <td>{{$email->created_at->format(config('mail-tracker.date-format'))}}</td>
                      <td>
                          <a href="{{route('mailTracker_ShowEmail',$email->id)}}" target="_blank">
                            View
                          </a>
                      </td>
                      <td>
                          @if($email->clicks > 0)
                              <a href="{{route('mailTracker_UrlDetail',$email->id)}}">Url Report</a>
                          @else
                              No Clicks
                          @endif
                      </td>
                    </tr>
                @endforeach
                </table>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12 text-center">
                {!! $emails->render() !!}
            </div>
        </div>
    </div>
@endsection
