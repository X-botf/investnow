<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\PushNotificationTemplate;
use App\Models\SetTune;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    public function latestNotification()
    {
        $notifications = Notification::where('for', 'admin')->latest()->take(10)->get();
        $totalUnread = Notification::where('for', 'admin')->where('read', 0)->count();
        $totalCount = Notification::where('for', 'admin')->get()->count();
        $lucideCall = true;

        return view('global.__notification_data', compact('notifications', 'totalUnread', 'totalCount', 'lucideCall'))->render();
    }

    public function setTune()
    {
        $set_tunes = SetTune::all();

        return view('backend.setting.notification_tune.index', compact('set_tunes'));
    }

    //notify tune setting

    public function all()
    {
        $notifications = Notification::where('for', 'admin')->latest()->paginate(10);

        return view('backend.notification.index', compact('notifications'));
    }

    public function status($id)
    {
        $set_tune = SetTune::find($id);

        if ($set_tune->status == 0) {
            $set_tune->status = 1;
            $set_tune->save();

            SetTune::whereNot('id', $id)->update(['status' => false]);

            notify()->success(__('Settings has been saved'));

            return back();
        }
        $set_tune->status = 0;
        $set_tune->save();

        SetTune::where('id', SetTune::first()->id)->update(['status' => true]);

        notify()->success(__('Settings has been saved'));

        return back();

    }

    //notification template
    public function template(Request $request)
    {
        $perPage = $request->perPage ?? 15;
        $order = $request->order ?? 'asc';
        $search = $request->search ?? null;
        $status = $request->status ?? 'all';
        $notifications = PushNotificationTemplate::order($order)
            ->search($search)
            ->status($status)
            ->paginate($perPage);

        return view('backend.push_notification.template', compact('notifications'));
    }

    public function editTemplate($id)
    {
        $template = PushNotificationTemplate::find($id);

        return view('backend.push_notification.edit', compact('template'));
    }

    public function updateTemplate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message_body' => 'required',
        ]);

        if ($validator->fails()) {
            notify()->error($validator->errors()->first(), 'Error');

            return redirect()->back();
        }

        $input = $request->all();
        $data = [
            'message_body' => nl2br($input['message_body']),
            'title' => $input['title'],
            'status' => $input['status'],
        ];

        $template = PushNotificationTemplate::find($input['id']);

        $template->update($data);

        notify()->success(__('Push Notification Template Updated Successfully'));

        return redirect()->back();
    }

    public function readNotification($id)
    {
        if ($id == 0) {
            Notification::where('for', 'admin')->update(['read' => 1]);

            return redirect()->back();
        }
        $notification = Notification::find($id);

        if ($notification->read == 0) {
            $notification->read = 1;
            $notification->save();
        }

        return $notification->action_url == null ? back() : redirect()->to($notification->action_url);
    }

    public function UpdateNotification($id)
    {
        try {
            $notification = Notification::find($id);
            $notification->read = 1;
            $notification->save();
            $status = 'success';
            $message = __('message marked as read');
        } catch (Exception $e) {

            $status = 'warning';
            $message = __('something is wrong');
        }
        notify()->$status($message, $status);

        return redirect()->back();
    }

    public function MessageAll()
    {
        return view('backend.user.message_send_all');
    }

    public function MessageSendAll(Request $request)
    {
        try {
            $notifications = [];
            $all_users = User::pluck('id');
            foreach ($all_users as $user_id) {
                $notifications[] = [
                    'user_id' => $user_id,
                    'for' => 'popup',
                    'notice' => $request->message,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            Notification::insert($notifications);

            notify()->success(__('Message sent to all users'), 'success');
        } catch (Exception $e) {
            notify()->warning(__('Something went wrong'), 'warning');
        }

        return redirect()->back();
    }
}
