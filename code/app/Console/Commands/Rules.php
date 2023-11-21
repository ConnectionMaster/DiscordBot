<?php

namespace App\Console\Commands;

use Discord\DiscordCommandClient;
use Discord\Parts\Channel\Message;
use Discord\Parts\User\Member;
use Discord\WebSockets\Intents;
use Illuminate\Console\Command;

class Rules extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discord:rules';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rules for Discord';

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
    public function handle()
    {
        $discord = null;
        try {
            $discord = new DiscordCommandClient([
                'token' => env('DISCORD_TOKEN'),
                'prefix' => '.',
                'defaultHelpCommand' => false,
                'discordOptions' => [
                    'token' => env('DISCORD_TOKEN'),
                    'loadAllMembers' => true,
                    'intents' => Intents::getDefaultIntents() | Intents::GUILD_MEMBERS | Intents::MESSAGE_CONTENT | Intents::GUILD_MESSAGES
                ],
            ]);

            try {
                $discord->registerCommand('rules', function (Message $message) use ($discord) {
                    $command = strtolower(substr($message->content, 7));
                    switch ($command) {
                        case 'agree':
                            if ($message->channel->name === 'bot-commands') {
                                $guild = $discord->guilds->get('id', env('DISCORD_GUILD_ID'));
                                $guild->members->fetch($message->author->id)
                                    ->done(function (Member $member) use ($message) {
                                        $member->addRole(env('DISCORD_GUILD_MEMBER_ROLE_ID'))->done(function () use ($message) {
                                            $message->reply('Welcome!');
                                        }, function ($e) use ($message) {
                                            $message->reply("you're already a member.");
                                        });
                                    });
                            }
                            break;
                        case 'list':
                            $guild = $discord->guilds->get('id', env('DISCORD_GUILD_ID'));
                            $response = "**Rules:**".PHP_EOL.PHP_EOL.
                                "**1)** Follow Discord TOS".PHP_EOL.
                                "**2)** I believe in the 1st amendment. Don't be stupid and post anything that violates laws! No doxxing, making threats of physical harm or anything that will make me need to speak with law enforcement.".PHP_EOL.
                                "**3)** At least try to get along, but if you can't/won't I will turn this server around and make you regret it!".PHP_EOL.
                                "**4)** Again, DON'T BE STUPID!".PHP_EOL.
                                "**5)** Agree to these terms in <#{$guild->channels->get('name', 'bot-commands')->id}> by typing `.rules agree`";
                            $message->channel->sendMessage($response);
                            break;
                        default:
                            $message->reply('you need to "agree".');
                            break;
                    }

                });
            } catch (\Exception $exception) {
                print_r($exception->getMessage());
            }

            $discord->run();
        } catch (\Exception $exception) {
            print_r($exception->getMessage() . PHP_EOL);
            echo "Cannot connect to Discord." . PHP_EOL;
        }

        return 0;
    }
}
