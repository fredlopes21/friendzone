<?php

class Post
{
  private $con, $user;

  public function __construct($con, $username)
  {
    $this->con = $con;
    $this->user = new User($con, $username);
  }

  public function submitPost($body, $userTo)
  {
    $body = strip_tags($body);
    $body = str_replace('\r\n', '\n', $body);
    $body = nl2br($body);

    $checkEmpty = preg_replace('/\s+/', '', $body);

    if ($checkEmpty != "") {

      $addedBy = $this->user->getUsername();

      if ($userTo == $addedBy) {
        $userTo = "none";
      }

      $dateAdded = date("Y-m-d H:i:s");
      $userClosed = 'no';
      $deleted = 'no';
      $likes = 0;

      $query = $this->con->prepare("INSERT INTO posts (body, added_by, user_to, date_added, user_closed, deleted, likes) VALUES(:body, :addedBy, :userTo, :dateAdded, :userClosed, :deleted, :likes)");
      $query->execute([
        ':body' => $body,
        ':addedBy' => $addedBy,
        ':userTo' => $userTo,
        ':dateAdded' => $dateAdded,
        ':userClosed' => $userClosed,
        ':deleted' => $deleted,
        ':likes' => $likes
      ]);
      $returnedID = $this->con->lastInsertId();

      $numPosts = $this->user->getPostCount();
      $numPosts++;

      $postCountQuery = $this->con->prepare("UPDATE users SET num_posts = :numPosts WHERE username = :un");
      $postCountQuery->execute([
        ':numPosts' => $numPosts,
        ':un' => $this->user->getUsername()
      ]);
    }
  }

  public function loadPosts($data, $limit)
  {
    $page = $data['page'];
    $userLoggedIn = $this->user->getUsername();

    if ($page == 1) {
      $start = 0;
    } else {
      $start = ($page - 1) * $limit;
    }

    $str = "";

    $query = $this->con->prepare("SELECT * FROM posts WHERE deleted = :deleted ORDER BY id DESC");
    $query->execute([':deleted' => 'no']);

    if ($query->rowCount() > 0) {

      $numIterations = 0;
      $count = 1;

      while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $id = $row['id'];
        $body = $row['body'];
        $addedBy = $row['added_by'];
        $dateAdded = $row['date_added'];

        if ($row['user_to'] == 'none') {
          $userTo = "";
        } else {
          $userToObj = new User($this->con, $row['user_to']);
          $userTo = "to <a href='" . $userToObj->getUsername() . "'>" . $userToObj->getFullName() . "</a>";
        }

        $addedByObj = new User($this->con, $row['added_by']);

        if ($addedByObj->isClosed()) {
          continue;
        }

        $userLoggedObj = new User($this->con, $userLoggedIn);

        if ($userLoggedObj->isFriend($addedBy)) {

          if ($numIterations++ < $start) {
            continue;
          }

          if ($count > $limit) {
            break;
          } else {
            $count++;
          }

          $userDetailsQuery = $this->con->prepare("SELECT first_name, last_name, profile_pic FROM users WHERE username = :un");
          $userDetailsQuery->execute([':un' => $addedBy]);
          $userRow = $userDetailsQuery->fetch(PDO::FETCH_ASSOC);
          $firstName = $userRow['first_name'];
          $lastName = $userRow['last_name'];
          $profilePic = $userRow['profile_pic'];

          $str .= "
            <div class='card post my-3'>
    
              <div class='card-header'>
                <div class='media'>
                <div class='post-profile-pic pr-2'>
                  <img src='$profilePic' class='img-fluid rounded-circle'>
                </div>
                  <div class='media-body'>
                  <div class='posted-by'>
                    <a href='$addedBy'>$firstName $lastName</a> $userTo
                    <small class='d-block'>" . $this->getDate($dateAdded) . "</small> 
                  </div>
                  </div>
                </div>
              </div>
    
              <div class='card-body'>
                <div class='post-body'>
                  $body
                </div>

                  <form id='comment-form-$id' class='my-3'>

                      <span class='comment-alert'></span>
                      
                      <div class='form-group'>
                        <div class='media'>
                            <img src='" . $this->user->getProfilePic() . "' class='img-fluid comment-profile-pic' alt='" . $this->user->getFullName() . "'>
                          <div class='media-body'>
                            <input type='text' name='post-body-$id' class='form-control comment-input' placeholder='Write a comment...'>
                          </div>
                        </div>
                      </div>

                      <div class='form-group'>

                        <div class='btn-group comment-like-btns'>      
                          <input type='hidden' value='$id'>

                          <button onclick='postComment(this)' name='post-comment-$id' class='btn btn-outline-secondary'>
                          <i class='far fa-comment-alt'></i> Comment
                          </button>

                          <button onclick='likeStatus(this)' name='like-status-$id' class='btn btn-outline-secondary'>

                            " . $this->displayLikeBtn($id) . "
                            
                          </button>
                        </div>

                      </div>

                  </form>

                  <hr>
      
                  <p class='post-stats'>" . $this->getCommentCount($id) . ", " . $this->getLikeCount($id) . "</p>

                  <div class='comments'>
                    
                    " . $this->loadComments($id) . "

                  </div>

              </div>
    
            </div>

          ";
        }
      }

      if ($count > $limit) {
        $str .= "
        <input type='hidden' class='next-page' value='" . ($page + 1) . "'>
        <input type='hidden' class='no-posts' value='false'>";
      } else {
        $str .= "
        <input type='hidden' class='no-posts' value='true'>
        <p class='text-center'>No more posts to show.</p>";
      }
    }


    echo $str;
  }
  public function getDate($dateAdded)
  {
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

    return $timeMessage;
  }
  private function displayLikeBtn($postID)
  {
    $checkLikeQuery = $this->con->prepare("SELECT * FROM likes WHERE username = :un AND post_id = :postID");
    $checkLikeQuery->execute([
      ':un' => $this->user->getUsername(),
      ':postID' => $postID
    ]);

    if ($checkLikeQuery->rowCount() > 0) {
      return "
        <span class='liked'>
          <i class='fas fa-thumbs-up'></i> Liked
        </span>
      ";
    } else {
      return "
        <span class='like'>
          <i class='far fa-thumbs-up'></i> Like
        </span>
      ";
    }
  }
  private function loadComments($postID)
  {
    $getCommentsQuery = $this->con->prepare("SELECT * FROM comments WHERE post_id = :postID ORDER BY id DESC");
    $getCommentsQuery->execute([':postID' => $postID]);

    if ($getCommentsQuery->rowCount() != 0) {

      $commentStr = "";

      while ($comment = $getCommentsQuery->fetch(PDO::FETCH_ASSOC)) {
        $commentBody = $comment['post_body'];
        $postedTo = $comment['posted_to'];
        $postedBy = $comment['posted_by'];
        $dateAdded = $comment['date_added'];
        $removed = $comment['removed'];

        $userObj = new User($this->con, $postedBy);

        $commentStr .= "
          <div class='comment pb-3'>
            <div class='media'>
              <img src='" . $userObj->getProfilePic() . "' class='img-fluid comment-profile-pic' alt='" . $userObj->getFullName() . "'>
              <div class='media-body'>  
                <div class='comment-body'>
                  <a href='" . $userObj->getUsername() . "'>" . $userObj->getFullName() . "</a>
                  $commentBody
                </div>
                <small class='d-block pl-2'>" . $this->getDate($dateAdded) . "</small>
              </div>
              </div>
          </div>
        ";
      }

      return $commentStr;
    }
  }
  private function getCommentCount($postID)
  {
    $commentCountQuery = $this->con->prepare("SELECT * FROM comments WHERE post_id = :postID");
    $commentCountQuery->execute([':postID' => $postID]);
    if ($commentCountQuery->rowCount() == 1) {
      return "<span class='comment-count-number'>" . $commentCountQuery->rowCount() . "</span> Comment";
    } else {
      return "<span class='comment-count-number'>" . $commentCountQuery->rowCount() . "</span> Comments";
    }
  }
  private function getLikeCount($postID)
  {
    $likeCountQuery = $this->con->prepare("SELECT * FROM likes WHERE post_id = :postID");
    $likeCountQuery->execute([':postID' => $postID]);
    if ($likeCountQuery->rowCount() == 1) {
      return "<span class='like-count-number'>" . $likeCountQuery->rowCount() . "</span> Like";
    } else {
      return "<span class='like-count-number'>" . $likeCountQuery->rowCount() . "</span> Likes";
    }
  }
}
