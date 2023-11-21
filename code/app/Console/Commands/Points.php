<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\Role;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Discord\DiscordCommandClient;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Embed\Field;
use Discord\Parts\Embed\Image;
use Discord\Parts\Guild\Guild;
use Discord\WebSockets\Intents;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

class Points extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discord:points';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        try {
            $discord = new DiscordCommandClient([
                'token' => env('DISCORD_TOKEN'),
                'prefix' => '.',
                'defaultHelpCommand' => false,
                'discordOptions' => [
                    'token' => env('DISCORD_TOKEN'),
                    'loadAllMembers' => true,
                    'intents' => Intents::getDefaultIntents() | Intents::GUILD_MEMBERS | Intents::MESSAGE_CONTENT
                ],

            ]);

            try {
                $discord->registerCommand('points', function (Message $message) use ($discord) {
                    $command = strtolower(substr($message->content, 8));
                    if ($command === '') {
                        $this->showHelp($discord, $message->channel_id);
                    } else {
                        try {
                            $m = $this->getMember($message->author->id, $message->guild_id);
                            if (is_null($m->id)) {
                                throw new Exception('You are not in the points program');
                            }
                            switch ($command) {
                                case 'bal':
                                case 'balance':
                                    $message->reply('Points: ' . $m->balance);
                                    break;
                                case 'send':

                                    break;
                                case 'shop':
                                    $message->reply('todo');
                                    break;
                                default:
                                    $this->showHelp($discord, $message->channel_id);

                            }
                        }
                        catch(Exception $exception) {
                            $message->reply($exception->getMessage());
                            $this->showHelp($discord, $message->channel_id);
                        }
                    }
                });
                $discord->registerCommand('balance', function (Message $message) use ($discord) {
                    try {
                        $m = $this->getMember($message->author->id, $message->guild_id);

                        if (is_null($m->id)) {
                            throw new Exception('You are not in the points program');
                        }
                        $message->reply('Points: ' . $m->balance);
                    }
                    catch(Exception $exception) {
                        $message->reply($exception->getMessage());
                        $this->showHelp($discord, $message->channel_id);
                    }
                });
                $discord->registerAlias('bal', 'balance');
                $discord->registerAlias('$', 'balance');

                $discord->registerCommand('send', function (Message $message) use ($discord) {
                    $command = strtolower(substr($message->content, 6));
                    if ($command === '') {
                        $this->showHelp($discord, $message->channel_id);
                    } else {
                        try {
                            $m = $this->getMember($message->author->id, $message->guild_id);
                            if (is_null($m->id)) {
                                throw new Exception('You are not in the points program');
                            }
                            $args = preg_split("/\s/", $command);
                            if (count($args) !== 2) {
                                throw new Exception('Invalid number of parameters.');
                            }
                            $amount = $args[0];
                            $recipient = $args[1];
                            $recipientId = filter_var($recipient, FILTER_SANITIZE_NUMBER_INT);
                            $r = $this->getMember($recipientId, $message->guild_id);
                            if (is_null($r->id)) {
                                throw new Exception("$recipient is not in the points program.");
                            }
                            if ($m->balance >= $amount) {
                                $m->balance -= $amount;
                                $r->balance += $amount;
                                $m->save();
                                $r->save();
                                $message->reply('Sending ' . $amount . ' to ' . $recipient);
                            } else {
                                throw new Exception('Insufficient points for transfer');
                            }
                        }
                        catch(Exception $exception) {
                            $message->reply($exception->getMessage());
                        }
                    }
                });

                $discord->registerCommand('shop', function (Message $message) use ($discord) {
                    $r = Role::where(['available'=>true])->get();
                    $roles = [];
                    foreach ($r as $role) {
                        $roles[] = new Field(
                            $discord,
                            [
                                'name' => $role->id . ') ' . $role->name,
                                'value' => $role->value,
                                'inline' => true,
                            ]
                        );
                    }
                    $discord->getChannel($message->channel_id)->sendEmbed(
                        new Embed(
                            $discord,
                            [
                                'title' => '__**Points Shop**__',
                                'type' => 'rich',
                                'description' => 'BUY ROLES!',
                                'color' => 0xff0000,
                                'thumbnail' => new Image(
                                    $discord,
                                    [
                                        'url' => 'https://emojipedia-us.s3.dualstack.us-west-1.amazonaws.com/thumbs/120/emojidex/112/convenience-store_1f3ea.png',
                                    ]
                                ),
                                'fields' => $roles
                            ]
                        )
                    )->done(function(Message $message) {});
                });
                $discord->registerCommand('buy', function (Message $message) use ($discord) {
                    $command = strtolower(substr($message->content, 5));
                    if ($command === '') {
                        $this->showHelp($discord, $message->channel_id);
                    } else {
                        try {
                            $m = $this->getMember($message->author->id, $message->guild_id);
                            if (is_null($m->id)) {
                                throw new Exception('You are not in the points program');
                            }
                            $args = preg_split("/\s/", $command);
                            if (count($args) !== 1) {
                                throw new Exception('Invalid number of parameters.');
                            }
                            $itemNumber = (int) $args[0];
                            $role = Role::where(['id' => $itemNumber])->first();
                            if ($m->balance >= $role->value) {
                                $discord->guilds->fetch($m->guild_id)
                                    ->done(function(Guild $guild) use ($m, $role) {
                                        $guild->members->fetch($m->id)
                                            ->done(function(\Discord\Parts\User\Member $member) use ($m, $role) {
                                                $member->addRole($role->roleId);
                                            });
                                    });
                                $m->balance -= $role->value;
                                $m->update();
                                $message->reply("Purchased $role->name for $role->value");
                            } else {
                                throw new Exception('Insufficient points for purchase');
                            }
                        }
                        catch(Exception $exception) {
                            $message->reply($exception->getMessage());
                        }
                    }
                });

                $discord->registerCommand('daily', function (Message $message) use ($discord) {
                    try {
                        $m = $this->getMember($message->author->id, $message->guild_id);

                        if (is_null($m->id)) {
                            throw new Exception('You are not in the points program');
                        }

                        if (is_null($m->lastDaily)) {
                            $m->lastDaily = new Carbon('last week');
                        }
                        $lastDaily = new Carbon($m->lastDaily);
                        $canReceive = $lastDaily->diffInDays(Carbon::now());

                        $lastTimely = new Carbon($m->lastTimely);
                        $resetTimely = $lastTimely->diffInDays(Carbon::now());
                        if ($resetTimely > 1) {
                            $m->streakTimely = 0;
                        }

                        if ($canReceive > 0) {
                            $prizeAmount = 10 * (1 + $m->streakTimely);
                            $m->balance += $prizeAmount;
                            $m->lastDaily = Carbon::now();
                            $replyText = "You received $prizeAmount points!" . PHP_EOL .
                                "You had a " . (1 + $m->streakTimely) . "x modifier from `.timely`.";
                        } else {
                            $nextDaily = CarbonInterval::make((Carbon::now())->diff($lastDaily->addDay()))->forHumans();
                            $replyText = 'You have already received your daily prize.' . PHP_EOL .
                                'Time until next prize can be claimed: ' . $nextDaily;
                        }
                        $message->reply($replyText);

                        $m->save();
                    }
                    catch(Exception $exception) {
                        $message->reply($exception->getMessage());
                        $this->showHelp($discord, $message->channel_id);
                    }
                });

                $discord->registerCommand('timely', function (Message $message) use ($discord) {
                    try {
                        $m = $this->getMember($message->author->id, $message->guild_id);

                        if (is_null($m->id)) {
                            throw new Exception('You are not in the points program');
                        }

                        if (is_null($m->lastDaily)) {
                            $m->lastTimely = new Carbon('last week');
                        }
                        $lastTimely = new Carbon($m->lastTimely);
                        $canReceive = $lastTimely->diffInDays(Carbon::now());

                        if ($canReceive > 0) {
                            $m->streakTimely += 1;
                            $m->lastTimely = Carbon::now();
                            $replyText = "You now have a " . ($m->streakTimely + 1) . "x streak";
                        } else {
                            $nextTimely = CarbonInterval::make((Carbon::now())->diff($lastTimely->addDay()))->forHumans();
                            $replyText = 'You have already received your timely modifier update.' . PHP_EOL .
                                'Time until you can update again: ' . $nextTimely;
                        }
                        $message->reply($replyText);

                        $m->save();
                    }
                    catch(Exception $exception) {
                        $message->reply($exception->getMessage());
                        $this->showHelp($discord, $message->channel_id);
                    }
                });

                $discord->registerCommand('flip', function (Message $message) use ($discord) {
                    $command = strtolower(substr($message->content, 6));
                    if ($command === '') {
                        $this->showHelp($discord, $message->channel_id);
                    } else {
                        $m = $this->getMember($message->author->id, $message->guild_id);
                        $bet = filter_var($command, FILTER_SANITIZE_NUMBER_INT);
                        if ($m->balance >= $bet) {
                            $m->balance -= $bet;
                            $m->save();
                            $e = new Embed(
                                $discord,
                                [
                                    'type' => 'rich',
                                    'color' => 0x00ff00,
                                    'title' => '__**Coin Flip**__',
                                    'description' => 'Flipping a coin....',
                                    'thumbnail' => new Image(
                                        $discord,
                                        [
                                            'url' => 'https://emojipedia-us.s3.dualstack.us-west-1.amazonaws.com/thumbs/120/whatsapp/273/coin_1fa99.png'
                                        ]
                                    ),
                                ]
                            );
                            $discord->getChannel($message->channel_id)
                                ->sendEmbed($e)
                                ->done(function(Message $message) use ($discord, $e, $m, $bet) {
                                    $loop = $discord->getLoop();
                                    $loop->addTimer(5, function() use ($discord, $message, $e, $m, $bet) {
                                        $result = (random_int(0,99)) % 2;
                                        $coin = $result ? 'tails' : 'heads';
                                        if ($result) {
                                            $m->balance += $bet * 1.5;
                                            $m->save();
                                        }
                                        $e->description = "It landed on $coin! " .
                                            (($result) ? "You WON!" : "Sorry you lost.");
                                        $discord->getChannel($message->channel_id)->sendEmbed($e);
                                        $message->delete();
                                    });
                                });
                        } else {
                            throw new Exception('You do not have enough points to make that bet.');
                        }
                    }
                });

                $discord->registerCommand('wheel', function (Message $message) use ($discord) {
                    $command = strtolower(substr($message->content, 7));
                    if ($command === '') {
                        $this->showHelp($discord, $message->channel_id);
                    } else {
                        $m = $this->getMember($message->author->id, $message->guild_id);
                        $bet = filter_var($command, FILTER_SANITIZE_NUMBER_INT);
                        if ($m->balance >= $bet) {
                            $m->balance -= $bet;
                            $m->save();

                            $v = [1.5, 1.7, 2.4, 0.2, 1.2, 0.1, 0.3, 0.5];
                            $e = new Embed(
                                $discord,
                                [
                                    'type' => 'rich',
                                    'color' => 0x00ff00,
                                    'title' => '__**Wheel of Money**__',
                                    'description' => 'Spinning the wheel.....',
                                    'thumbnail' => new Image(
                                        $discord,
                                        [
                                            'url' => 'https://static.vecteezy.com/system/protected/files/001/192/280/vecteezy_rainbow-spinning-wheel_1192280.png'
                                        ]
                                    ),
                                    'fields' => $this->getWheel($discord, $v),
                                ]
                            );

//                            $image = imagecreate(150, 150);
//
//                            $guzzleClient = new Client();
//                            $guzzleClient->post();

//                            $e = new Embed(
//                                $discord,
//                                [
//                                    'type' => 'image',
//                                    'color' => 0x00ff00,
//                                    'title' => '__**Wheel of Money**__',
//                                    'description' => 'Spinning the wheel.....',
//
//                                ]
//                            );

                            $discord->getChannel($message->channel_id)
                                ->sendEmbed($e)
                                ->done(function(Message $message) use ($discord, $e, $m, $bet, $v) {
                                    $loop = $discord->getLoop();
                                    $loop->addTimer(5, function() use ($discord, $message, $e, $m, $bet, $v) {
                                        $result = (random_int(0,79)) % 8;
                                        for ($i = 0; $i < $result; $i++) {
                                            array_push($v, array_shift($v));
                                        }
                                        $prize = $bet * $v[1];
                                        $m->balance += $prize;
                                        $m->save();
                                        $e->fields = $this->getWheel($discord, $v);
                                        $e->description = "Wheel landed on $v[1]x." . PHP_EOL . "You win $prize!";
                                        $discord->getChannel($message->channel_id)->sendEmbed($e);
                                        $message->delete();
                                    });
                                });
                        } else {
                            throw new Exception('You do not have enough points to make that bet.');
                        }

                    }
                });
            } catch (Exception $exception) {
                print_r($exception->getMessage());
            }

            $discord->run();
        } catch (Exception $exception) {
            print_r($exception->getMessage() . PHP_EOL);
            echo "Cannot connect to Discord." . PHP_EOL;
        }

        return 0;
    }

    private function getMember(int $id, int $guild_id): Member
    {
        return Member::where([
            'id' => $id,
            'guild_id' => $guild_id,
        ])->first();
    }

    private function getWheel(DiscordCommandClient $dcc, array $values): array
    {
        foreach ($values as $key => $value) {
            $values[$key] = $value . 'x';
        }
        array_splice($values, 4, 0, ':arrow_up:');
        $wheel = [];
        $decoration = ['/', '-', '\\', '|', 'O', '|', '\\', '-', '/'];
        foreach ($values as $key => $value) {
            $wheel[] = new Field($dcc, [
                'name'  => $value,
                'value' => $decoration[$key],
                'inline' => true
            ]);
        }

        return $wheel;
    }

    private function showHelp($discord, $channelId): void
    {
        $discord->getChannel($channelId)->sendEmbed(
            new Embed(
                $discord,
                [
                    'type' => 'rich',
                    'color' => 0x00ff00,
                    'title' => '__**Points System**__',
                    'description' => 'Make sure you run `.collect` to be in the system.',
                    'thumbnail' => new Image(
                        $discord,
                        [
                            'url' => 'https://emojipedia-us.s3.dualstack.us-west-1.amazonaws.com/thumbs/120/facebook/65/money-bag_1f4b0.png'
                        ]
                    ),
                    'fields' => [
                        new Field(
                            $discord,
                            [
                                'name'  => '`.$ | .bal | .balance`',
                                'value' => 'shows current point balance',
                                'inline' => false
                            ]
                        ),
                        new Field(
                            $discord,
                            [
                                'name'  => '`.send # @user`',
                                'value' => 'sends @user `#` number of points',
                                'inline' => false
                            ]
                        ),
                        new Field(
                            $discord,
                            [
                                'name'  => '`.shop`',
                                'value' => 'see what is available at the shop',
                                'inline' => false
                            ]
                        ),
                        new Field(
                            $discord,
                            [
                                'name'  => '`.buy #`',
                                'value' => 'buy item from shop',
                                'inline' => false
                            ]
                        ),
                        new Field(
                            $discord,
                            [
                                'name'  => '`.daily`',
                                'value' => 'get a daily bonus of 10 pts x your `.timely` streak',
                                'inline' => false
                            ]
                        ),
                        new Field(
                            $discord,
                            [
                                'name'  => '`.timely`',
                                'value' => 'try to get a 7 day streak bonus',
                                'inline' => false
                            ]
                        ),
                        new Field(
                            $discord,
                            [
                                'name'  => '`.flip #`',
                                'value' => 'Place a bet of `#`, flip a coin, if it lands on heads you win 1.5x your bet',
                                'inline' => false
                            ]
                        ),
                        new Field(
                            $discord,
                            [
                                'name'  => '`.wheel #`',
                                'value' => 'Place a bet of `#`, spin the wheel, win a prize',
                                'inline' => false
                            ]
                        ),
                    ]
                ]
            )
        );
    }
}
