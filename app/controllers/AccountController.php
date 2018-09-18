<?php
namespace DPayExplorer\Controllers;

use MongoDB\BSON\UTCDateTime;

use DPayExplorer\Models\Account;
use DPayExplorer\Models\AccountHistory;
use DPayExplorer\Models\AuthorReward;
use DPayExplorer\Models\Block;
use DPayExplorer\Models\Comment;
use DPayExplorer\Models\CurationReward;
use DPayExplorer\Models\Follow;
use DPayExplorer\Models\Reblog;
use DPayExplorer\Models\Vote;
use DPayExplorer\Models\Statistics;
use DPayExplorer\Models\Pow;
use DPayExplorer\Models\Transfer;
use DPayExplorer\Models\VestingDeposit;
use DPayExplorer\Models\VestingWithdraw;
use DPayExplorer\Models\WitnessMiss;
use DPayExplorer\Models\WitnessHistory;
use DPayExplorer\Models\WitnessVote;

class AccountController extends ControllerBase
{

  private function getAccount()
  {
    $account = strtolower($this->dispatcher->getParam("account"));
    $cacheKey = 'account-'.$account;
    // Load account from the database
    $this->view->account = Account::findFirst(array(
      array(
        'name' => $account
      )
    ));
    // Check the cache for this account from the blockchain
    $cached = $this->memcached->get($cacheKey);
    // No cache, let's load
    if($cached === null) {
      $this->view->live = $this->dpayd->getAccount($account);
      $this->memcached->save($cacheKey, $this->view->live, 60);
    } else {
      // Use cache
      $this->view->live = $cached;
    }
    return $account;
  }

  public function viewAction()
  {
    $account = $this->getAccount();
    $this->view->props = $this->dpayd->getProps();
    try {
      $this->view->activity = array_reverse($this->dpayd->getAccountHistory($account));
    } catch (Exception $e) {
      $this->view->activity = false;
    }
    $this->view->mining = Pow::find(array(
      array(
        'witness' => $account,
      ),
      'sort' => array('_ts' => -1),
      'limit' => 100
    ));
    $this->view->chart = true;
    $this->view->pick("account/view");
  }

  public function propsAction()
  {
    $account = $this->getAccount();
    $this->view->history = WitnessHistory::find(array(
      ['owner' => $account],
      'sort' => array('created' => -1),
      'limit' => 100
    ));
    $this->view->pick("account/view");
  }

  public function postsAction()
  {
    $account = $this->getAccount();
    $this->view->comments = Comment::find(array(
      array(
        'author' => $account,
        'depth' => 0,
      ),
      'sort' => array('created' => -1),
      'limit' => 100
    ));
    $this->view->total_payouts = 0;
    $this->view->total_pending = 0;
    foreach($this->view->comments as $comment) {
      if($comment->total_pending_payout_value) {
        $this->view->total_pending += $comment->total_pending_payout_value;
      }
      if($comment->total_payout_value) {
        $this->view->total_payouts += $comment->total_payout_value;
      }
    }
    $this->view->chart = true;
    $this->view->pick("account/view");
  }

  public function votesAction()
  {
    $account = $this->getAccount();
    $this->view->filter = $this->request->get('type');
    switch($this->view->filter) {
      case "incoming":
        $query = array(
          'author' => $account,
        );
        break;
      default:
        $query = array(
          'voter' => $account,
        );
        break;
    }
    $this->view->votes = Vote::find(array(
      $query,
      'sort' => array('_ts' => -1),
      'limit' => 200
    ));
    $this->view->chart = true;
    $this->view->pick("account/view");
  }

  public function repliesAction()
  {
    $account = $this->getAccount();
    $this->view->replies = Comment::find(array(
      array(
        'author' => $account,
        'depth' => ['$gt' => 0],
      ),
      'sort' => array('created' => -1),
      'limit' => 100
    ));
    $this->view->pick("account/view");
  }

  public function followersAction()
  {
    $account = $this->getAccount();
    $this->view->followers = Follow::find([
      ["following" => $account],
      "sort" => ['_ts' => -1]
    ]);
    $this->view->chart = true;
    $this->view->pick("account/view");
  }

  public function followersWhalesAction()
  {
    $account = $this->getAccount();
    $this->view->followers = Account::find([
      ['name' => ['$in' => $this->view->account->followers]],
      'sort' => ['vesting_shares' => -1],
    ]);
    $this->view->pick("account/view");
  }

  public function followingAction()
  {
    $account = $this->getAccount();
    $this->view->followers = Follow::find([
      ["follower" => $account],
      "sort" => ['_ts' => -1]
    ]);
    $this->view->pick("account/view");
  }

  public function witnessAction()
  {
    $account = $this->getAccount();
    $this->view->votes = WitnessVote::agg([
      ['$match' => [
        'witness' => $account
      ]],
      ['$sort' => [
        '_ts' => -1
      ]],
      ['$limit' => 100],
      ['$lookup' => [
        'from' => 'account',
        'localField' => 'account',
        'foreignField' => 'name',
        'as' => 'voter'
      ]],
    ]);
    $this->view->witnessing = Account::agg([
      ['$match' => [
          'witness_votes' => $account,
      ]],
      ['$project' => [
        'name' => '$name',
        'weight' => ['$sum' => ['$vesting_shares', '$proxy_witness']]
      ]],
      ['$sort' => ['weight' => -1]]
    ])->toArray();
    $this->view->witness_votes = array_sum(array_map(function($item) {
      return $item['weight'];
    }, $this->view->witnessing));
    $this->view->chart = true;
    $this->view->pick("account/view");
  }

  public function blocksAction()
  {
    $account = $this->getAccount();
    $this->view->mining = Block::find(array(
      array(
        'witness' => $account,
      ),
      'sort' => array('_ts' => -1),
      'limit' => 100
    ));
    $this->view->chart = true;
    $this->view->pick("account/view");
  }

  public function missedAction()
  {
    $account = $this->getAccount();
    $this->view->mining = WitnessMiss::find(array(
      array(
        'witness' => $account,
      ),
      'sort' => array('date' => -1),
      'limit' => 100
    ));
    $this->view->pick("account/view");
  }

  public function reblogsAction()
  {
    $account = $this->getAccount();
    $page = $this->view->page = (int) $this->request->get('page') ?: 1;
    $this->view->reblogs = Reblog::find(array(
      array(
        'account' => $account,
      ),
      'sort' => array('_ts' => -1),
      'limit' => 100,
      'skip' => 100 * ($page - 1)
    ));
    $this->view->pick("account/view");
  }

  public function rebloggedAction()
  {
    $account = $this->getAccount();
    $page = $this->view->page = (int) $this->request->get('page') ?: 1;
    $this->view->reblogs = Reblog::find(array(
      array(
        'author' => $account,
      ),
      'sort' => array('_ts' => -1),
      'limit' => 100,
      'skip' => 100 * ($page - 1)
    ));
    $this->view->pick("account/view");
  }

  public function proxiedAction()
  {
    $account = $this->getAccount();
    $this->view->proxied = Account::find(array(
      array('proxy' => $account)
    ));
    $this->view->pick("account/view");
  }

  public function curationAction()
  {
    $account = $this->getAccount();
    $this->view->curation = CurationReward::agg(array(
      ['$match' => [
        'curator' => $account,
        ]],
      ['$group' => [
        '_id' => [
          'doy' => ['$dayOfYear' => '$_ts'],
          'year' => ['$year' => '$_ts'],
          'month' => ['$month' => '$_ts'],
          'week' => ['$week' => '$_ts'],
          'day' => ['$dayOfMonth' => '$_ts']
        ],
        '_ts' => ['$first' => '$_ts'],
        'reward' => ['$sum' => '$reward'],
        'votes' => ['$sum' => 1],
        ]],
      ['$sort' => [
        '_ts' => -1,
        ]],
    ));
    $this->view->stats = CurationReward::agg([
      [
        '$match' => [
          'curator' => $account,
          '_ts' => [
            '$gte' => new UTCDateTime(strtotime("-30 days") * 1000),
          ],
        ]
      ],
      [
        '$group' => [
          '_id' => '$curator',
          'day' => ['$sum' => ['$cond' => [
            [
              '$gte' => [
                '$_ts',
                new UTCDateTime(strtotime("-1 days") * 1000)
              ]
            ],
            '$reward',
            0
          ]]],
          'week' => ['$sum' => ['$cond' => [
            [
              '$gte' => [
                '$_ts',
                new UTCDateTime(strtotime("-7 days") * 1000)
              ]
            ],
            '$reward',
            0
          ]]],
          'month' => ['$sum' => ['$cond' => [
            [
              '$gte' => [
                '$_ts',
                new UTCDateTime(strtotime("-30 days") * 1000)
              ]
            ],
            '$reward',
            0
          ]]],
        ]
      ]
    ])->toArray();
    $this->view->chart = true;
    $this->view->pick("account/view");
  }

  public function curationDateAction() {
    $account = $this->getAccount();
    $this->view->date = $this->dispatcher->getParam("date");
    $this->view->curation = CurationReward::find(array(
      array(
        'curator' => $account,
        '_ts' => [
          '$gte' => new UTCDateTime(strtotime($this->view->date) * 1000),
          '$lte' => new UTCDateTime((strtotime($this->view->date) + 86400) * 1000),
        ]
      ),
      'sort' => array('_ts' => -1),
      'skip' => $limit * ($page - 1),
      'limit' => $limit,
    ));
    $this->view->pick("account/view");
  }

  public function authoringAction()
  {
    $account = $this->getAccount();
    $this->view->page = $page = (int) $this->request->get("page") ?: 1;
    $limit = 50;
    $this->view->authoring = AuthorReward::find(array(
      array(
        'author' => $account
      ),
      'sort' => array('_ts' => -1),
      'skip' => $limit * ($page - 1),
      'limit' => $limit,
    ));
    $this->view->pages = ceil(AuthorReward::count(array(
      array('curator' => $account)
    )) / $limit);
    $this->view->chart = true;
    $this->view->pick("account/view");
  }

  public function powerupAction()
  {
    $account = $this->getAccount();
    $this->view->powerup = VestingDeposit::find(array(
      array('to' => $account),
      'sort' => array('_ts' => -1)
    ));
    $this->view->chart = true;
    $this->view->pick("account/view");
  }

  public function powerdownAction()
  {
    $account = $this->getAccount();
    $this->view->powerdown = VestingWithdraw::find(array(
      array('from_account' => $account),
      'sort' => array('_ts' => -1)
    ));
    $this->view->chart = true;
    $this->view->pick("account/view");
  }

  public function transfersAction()
  {
    $account = $this->getAccount();
    $this->view->page = $page = (int) $this->request->get("page") ?: 1;
    $limit = 50;
    $this->view->transfers = Transfer::find(array(
      array(
        '$or' => array(
          array('from' => $account),
          array('to' => $account),
        )
      ),
      'sort' => array('_ts' => -1),
      'skip' => $limit * ($page - 1),
      'limit' => $limit,
    ));
    $this->view->pages = ceil(Transfer::count(array(
      array(
        '$or' => array(
          array('from' => $account),
          array('to' => $account),
        )
      ),
    )) / $limit);
    $this->view->chart = true;
    $this->view->pick("account/view");
  }

  public function dataAction()
  {
    $account = $this->getAccount();
    $this->view->pick("account/view");
  }
}
