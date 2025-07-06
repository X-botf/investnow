<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Traits\ImageUpload;
use App\Traits\NotifyTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;

class TicketController extends Controller
{
    use ImageUpload, NotifyTrait;

    /**
     * Display a listing of the resource.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:support-ticket-list|support-ticket-action', ['only' => ['index']]);
        $this->middleware('permission:support-ticket-action', ['only' => ['closeNow', 'reply', 'show']]);

    }

    public function index(Request $request)
    {
        $search = $request->query('query') ?? null;
        $status = $request->query('status') ?? null;

        $tickets = Ticket::query()
            ->when(! blank($status), function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->search($search)
            ->paginate(10);

        $title = __('All Tickets');

        return view('backend.ticket.index', compact('tickets', 'title'));
    }

    public function show($uuid)
    {
        $ticket = Ticket::uuid($uuid);

        return view('backend.ticket.show', compact('ticket'));
    }

    public function closeNow($uuid)
    {
        Ticket::uuid($uuid)->close();
        notify()->success('Ticket Closed successfully', 'success');

        return Redirect::route('admin.ticket.index');

    }

    public function reply(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required',
        ]);

        if ($validator->fails()) {
            notify()->error($validator->errors()->first(), 'Error');

            return redirect()->back();
        }

        $input = $request->all();

        $adminId = \Auth::id();

        $data = [
            'model' => 'admin',
            'user_id' => $adminId,
            'message' => nl2br($input['message']),
            'attach' => $request->hasFile('attach') ? self::imageUploadTrait($input['attach']) : null,
        ];

        $ticket = Ticket::uuid($input['uuid']);

        $ticket->messages()->create($data);

        $shortcodes = [
            '[[full_name]]' => $ticket->user->full_name,
            '[[email]]' => $ticket->user->email,
            '[[subject]]' => $input['uuid'],
            '[[title]]' => $ticket->title,
            '[[message]]' => $data['message'],
            '[[status]]' => $ticket->status,
            '[[site_title]]' => setting('site_title', 'global'),
            '[[site_url]]' => route('home'),
        ];

        $this->mailNotify($ticket->user->email, 'user_support_ticket', $shortcodes);

        notify()->success('Ticket Reply successfully', 'success');

        return Redirect::route('admin.ticket.show', $ticket->uuid);

    }
}
