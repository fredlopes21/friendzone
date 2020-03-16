<?php

class Message
{
  private $con, $user;

  public function __construct($con, $username)
  {
    $this->con = $con;
    $this->user = new User($con, $username);
  }

  public function getMostRecentUser()
  {
    $userLoggedIn = $this->user->getUsername();

    $query = $this->con->prepare("SELECT user_to, user_from FROM messages WHERE user_to = :un OR user_from = :un ORDER BY id DESC LIMIT 1");
    $query->execute([':un' => $userLoggedIn]);

    if ($query->rowCount() == 0) {
      return false;
    }

    $row = $query->fetch(PDO::FETCH_ASSOC);
    $userTo = $row['user_to'];
    $userFrom = $row['user_from'];

    if ($userTo != $userLoggedIn) {
      return $userTo;
    } else {
      return $userFrom;
    }
  }

  public function sendMessage($userTo, $body, $date)
  {
    if ($body != "") {
      $userLoggedIn = $this->user->getUsername();

      $query = $this->con->prepare("INSERT INTO messages (user_to, user_from, body, date, opened, viewed, deleted) VALUES(:userTo, :userFrom, :body, :date, :opened, :viewed, :deleted)");
      $query->execute([
        ':userTo' => $userTo,
        ':userFrom' => $userLoggedIn,
        ':body' => $body,
        ':date' => $date,
        ':opened' => 'no',
        ':viewed' => 'no',
        ':deleted' => 'no'
      ]);
    }
  }

  public function getMessages($otherUser)
  {
    $userLoggedIn = $this->user->getUsername();
    $data = "";

    $query = $this->con->prepare("UPDATE messages SET opened = :yes WHERE user_to = :un AND user_from = :otherUser");
    $query->execute([':yes' => 'yes', ':un' => $userLoggedIn, ':otherUser' => $otherUser]);

    $getMessagesQuery = $this->con->prepare("SELECT * FROM messages WHERE (user_to = :un AND user_from = :otherUser) OR (user_from = :un AND user_to = :otherUser)");
    $getMessagesQuery->execute([':un' => $userLoggedIn, ':otherUser' => $otherUser]);

    while ($row = $getMessagesQuery->fetch(PDO::FETCH_ASSOC)) {
      $userTo = $row['user_to'];
      $userFrom = $row['user_from'];
      $body = $row['body'];

      if ($userTo == $userLoggedIn) {
        $data .= "
        <li class='list-group-item'>
          <span class='message message-green'>$body</span>
        </li>
        ";
      } else {
        $data .= "
        <li class='list-group-item'>
          <span class='message message-blue'>$body</span>
        </li>
        ";
      }
    }

    return $data;
  }

  public function getConvos()
  {
    $userLoggedIn = $this->user->getUsername();
    $data = "";
    $convos = [];

    $query = $this->con->prepare("SELECT user_to, user_from FROM messages WHERE user_to = :un OR user_from = :un ORDER BY id DESC");
    $query->execute([':un' => $userLoggedIn]);

    while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
      $userToPush = ($row['user_to'] != $userLoggedIn) ? $row['user_to'] : $row['user_from'];

      if (!in_array($userToPush, $convos)) {
        array_push($convos, $userToPush);
      }
    }

    foreach ($convos as $username) {
      $userFoundObj = new User($this->con, $username);
      $latestMessageDetails = $this->getLatestMessage($userLoggedIn, $username);

      $dots = (strlen($latestMessageDetails[1]) >= 30) ? "..." : "";
      $split = str_split($latestMessageDetails[1], 30);
      $split = $split[0] . $dots;

      $data .= "
      <div class='card convo'>
        <a href='messages.php?u=$username'>
          <div class='card-header'>
            <div class='media'>
              <img src='" . $userFoundObj->getProfilePic() . "' class='img-fluid convo-img' alt='" . $userFoundObj->getFullName() . "'>
              <div class='media-body'>
                <span>" . $userFoundObj->getFullName() . "</span>
                <small class='d-block'>" . $latestMessageDetails[2] . "</small>
              </div>
            </div>
          </div>
          <div class='card-body'>
            <small>" . $latestMessageDetails[0] . $split . "</small>
          </div>
          </a>
        </div>
      ";
    }

    return $data;
  }

  public function getLatestMessage($user, $user2)
  {
    $detailsArray = [];

    $query = $this->con->prepare("SELECT body, user_to, date FROM messages WHERE (user_to = :un AND user_from = :un2) OR (user_to = :un2 AND user_from = :un) ORDER BY id DESC LIMIT 1");
    $query->execute([':un' => $user, ':un2' => $user2]);

    $row = $query->fetch(PDO::FETCH_ASSOC);

    $sentBy = ($row['user_to'] == $user) ? "They said:<br>" : "You said:<br>";
    $dateAdded = $row['date'];

    $dateTimeNow = date('Y-m-d H:i:s');
    $startDate = new DateTime($dateAdded);
    $endDate = new DateTime($dateTimeNow);
    $interval = $startDate->diff($endDate);
    if ($interval->y >= 1) {
      if ($interval == 1) {
        $timeMessage = $interval->y . " year ago.";
      } else {
        $timeMessage = $interval->y . " years ago.";
      }
    } else if ($interval->m >= 1) {
      if ($interval->d == 0) {
        $days = " ago.";
      } else if ($interval->d == 1) {
        $days = $interval->d . " day ago.";
      } else {
        $days = $interval->d . " days ago.";
      }

      if ($interval->m == 1) {
        $timeMessage = $interval->m . " month" . $days;
      } else {
        $timeMessage = $interval->m . " months" . $days;
      }
    } else if ($interval->d >= 1) {
      if ($interval->d == 1) {
        $timeMessage = "Yesterday.";
      } else {
        $timeMessage = $interval->d . " days ago.";
      }
    } else if ($interval->h >= 1) {
      if ($interval->h == 1) {
        $timeMessage = $interval->h . " hour ago.";
      } else {
        $timeMessage = $interval->h . " hours ago.";
      }
    } else if ($interval->i >= 1) {
      if ($interval->i == 1) {
        $timeMessage = $interval->i . " minute ago.";
      } else {
        $timeMessage = $interval->i . " minutes ago.";
      }
    } else {
      if ($interval->s < 30) {
        $timeMessage = "Just now.";
      } else {
        $timeMessage = $interval->s . " seconds ago";
      }
    }

    array_push($detailsArray, $sentBy);
    array_push($detailsArray, $row['body']);
    array_push($detailsArray, $timeMessage);

    return $detailsArray;
  }
}
