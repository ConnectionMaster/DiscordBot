<?php

namespace App\Console\Commands;

use App\Models\Guild;
use App\Models\Member;
use Discord\Discord;
use Discord\DiscordCommandClient;
use Discord\Parts\Channel\Message;
use Discord\Parts\User\Member as DiscordMember;
use Discord\Parts\Guild\Guild as DiscordGuild;
use Discord\Parts\User\User;
use Discord\WebSockets\Event;
use Discord\WebSockets\Intents;
use Illuminate\Console\Command;

class MemberCollection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discord:member-collection';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $discord = null;
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
        }
        catch (\Exception $exception) {
            echo "Cannot connect to Discord.".PHP_EOL;
        }

        if (!is_null($discord)) {

            if (env('BOT_AUTO_MEMBER_COLLECT', true)) {
                $discord->on('init', function($discord) {
                    $discord->on(Event::GUILD_MEMBER_ADD, function(DiscordMember $member) {
                        $this->collect($member);
                    });
                });
            }

            if (env('BOT_MEMBER_COLLECT_ALLOW_MANUAL', true)) {
                try {
                    $discord->registerCommand('collect', function (Message $message) use ($discord) {
                        $command = strtolower(substr($message->content, 9));
                        if ($command === '') {
                            $this->collect($message->member);
                        } else {
                            $id = filter_var($command, FILTER_SANITIZE_NUMBER_INT);
                            $discord->users->fetch($id)->done(
                                function (User $user) use ($discord, $message) {
                                    $discord->guilds->fetch($message->guild_id)->done(
                                        function(DiscordGuild $guild) use ($user) {
                                            $this->collect($user, $guild->id, $guild->name);
                                        },
                                        function ($error) {
                                            ddd($error);
                                        }
                                    );
                                },
                                function ($error) {
                                    ddd($error);
                                }
                            );
                        }
                    });
                } catch (\Exception $exception) {
                    print_r($exception->getMessage());
                }
            }

            $discord->run();
        }
        return 0;
    }

    private function collect(DiscordMember|User $member, int $guildId = 0, string $guildName = ''): void
    {
        if ($guildId === 0) {
            $guildId = $member->guild->id;
        }
        if ($guildName === '') {
            $guildName = $member->guild->name;
        }
        $g = Guild::firstOrCreate([
            'id' => $guildId,
        ]);
        $g->name = $guildName;
        $g->save();

        $m = Member::firstOrCreate([
            'id' => $member->id,
            'guild_id' => $guildId,
        ]);
        $m->username = $member->username;
        $m->nick = $member->nick;
        $m->save();
    }
}
