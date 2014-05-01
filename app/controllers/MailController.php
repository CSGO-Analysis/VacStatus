<?php
class MailController extends BaseController {

  public function showSub()
  {
    $userMail = mailList::whereSteamUserId(Session::get('user.id'))->first();

    $emailSendTime = Session::get('email.send');
    if(!is_null($emailSendTime)) {
      $emailSendTime = time() - $emailSendTime;

      if($emailSendTime > 3600) {
        $emailSendTime = (int)($emailSendTime / 3600). ' hours ago';
      } elseif($emailSendTime > 60) {
        $emailSendTime = (int)($emailSendTime / 60). ' mintues ago';
      } else {
        $emailSendTime .= ' seconds ago';
      }
    }

    return View::make('user.subscribe', array('userMail' => $userMail, 'emailSendTime' => $emailSendTime) );
  }

  public function doSub()
  {
    $userMail = mailList::whereSteamUserId(Session::get('user.id'))->first();

    $unsub = Input::get('unsub');
    if($unsub != null) {
      $userMail->delete();
      return Redirect::route('subscribe');
    }

    $setEmail = Input::get('setEmail');
    if($setEmail == null) return;

    $verificationCode = str_random(40);

    if($userMail != null) {
      if($setEmail == $userMail) return;
    } else {
      $userMail = new mailList;
      $userMail->steam_user_id = Session::get('user.id');
    }
    $userMail->email = $setEmail;
    $userMail->verify = $verificationCode;
    $userMail->save();

    return Redirect::route('subscribe');
  }

  public function sendVerification()
  {
    if(Session::get('email.send') != null && time() - Session::get('email.send') < 300) {
      return Redirect::route('subscribe');
    }

    $userMail = mailList::whereSteamUserId(Session::get('user.id'))->first();

    if($userMail != null && $userMail->verify != 'done') {
      $email = $userMail->email;

      Mail::send('emails.verification', Array('verify' => $userMail->verify), function($message) use ($userMail)
      {
        $message->to($userMail->email)->subject('You\'re Almost Done!');
      });

      Session::put('email.send', time());
    }

    return Redirect::route('subscribe');
  }

  public function verifyEmail($verificationCode = null)
  {

    if($verificationCode == null) return Redirect::route('home');

    $find = mailList::whereVerify($verificationCode)->first();

    if($find != null) {
      $find->verify = 'done';
      $find->save();
    }
    if(Session::get('user.in')) {
      return Redirect::route('subscribe');
    }

    return Redirect::route('home');

  }

  public function getASubscribedUser()
  {
    if(Cache::has('getLastCheckedUser')) {
      $getLastCheckedUser = Cache::get('getLastCheckedUser');
      $getNewUser = mailList::whereRaw('id > ? and verify = ?', array($getLastCheckedUser, 'done'))->first();

      if($getNewUser->id == null) {
        Cache::set('getLastCheckedUser', -1);
        return $this->getASubscribedUser();
      }

      return $getNewUser;
    }
    Cache::forever('getLastCheckedUser', -1);
    return $this->getASubscribedUser();
  }
}
?>
