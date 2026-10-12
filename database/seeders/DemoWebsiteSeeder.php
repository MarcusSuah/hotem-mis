<?php

namespace Database\Seeders;

use App\Enums\SystemRole;
use App\Models\HotelEvent;
use App\Models\Offer;
use App\Models\Page;
use App\Models\Post;
use App\Models\RatePlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Development-only website content: standard pages, offers tied to real
 * rate plans, a few events and journal articles.
 *
 * Reviews are deliberately not seeded. Every published review must come
 * from a real completed stay through the moderation workflow.
 */
class DemoWebsiteSeeder extends Seeder
{
    public function run(): void
    {
        if (Page::query()->exists()) {
            $this->command->info('Website demo content already present; skipped.');

            return;
        }

        $today = CarbonImmutable::today();
        $author = User::query()->where('email', Str::slug(SystemRole::MarketingManager->value).'@hms.test')->first();

        $pages = [
            ['About us', 'A calm, independent hotel in the heart of Monrovia.', "## Our story\n\nWe opened our doors to give travellers a quiet, well-run place to stay in Monrovia — whether you are here for meetings, family or the coast.\n\n## What to expect\n\n- Rooms prepared and inspected before every arrival\n- A restaurant serving Liberian classics and international dishes\n- A front desk team available around the clock", 0],
            ['House policies', 'Check-in, payments, children and pets.', "## Check-in and check-out\n\nCheck-in is from **14:00** and check-out is by **11:00**. Early arrival and late departure depend on availability and may carry a fee.\n\n## Payments\n\nA deposit confirms your booking. We accept cash, card, bank transfer and mobile money in US dollars or Liberian dollars at the day’s published rate.\n\n## Identification\n\nAll guests are asked to show a valid identity document at check-in.", 1],
            ['Privacy notice', 'How we handle your personal information.', "We collect the details needed to manage your stay — your name, contact details, identity document and payment records — and keep them only as long as the law and our accounting require.\n\nWe never sell your information. Card numbers are not stored by our systems.\n\nTo see or correct the information we hold about you, contact the front desk.", 2],
        ];

        foreach ($pages as [$title, $summary, $body, $order]) {
            Page::create(['title' => $title, 'summary' => $summary, 'body' => $body, 'is_published' => true, 'show_in_footer' => true, 'display_order' => $order]);
        }

        $plans = RatePlan::query()->where('is_public', true)->where('is_active', true)->get()->keyBy('code');

        if ($plans->has('SAVER')) {
            Offer::create([
                'title' => 'Advance Saver',
                'summary' => 'Plan ahead and save on stays of two nights or more.',
                'body' => "Book at least two nights and enjoy our lowest room price. Rooms and dates are subject to availability.\n\nThis rate is non-refundable once confirmed — see the cancellation terms when you book.",
                'rate_plan_id' => $plans['SAVER']->id,
                'is_published' => true,
                'display_order' => 0,
            ]);
        }

        if ($plans->has('BB')) {
            Offer::create([
                'title' => 'Bed & Breakfast',
                'summary' => 'Start each day with breakfast in our restaurant.',
                'body' => 'Breakfast for every guest in the room is included for each night of your stay.',
                'rate_plan_id' => $plans['BB']->id,
                'is_published' => true,
                'display_order' => 1,
            ]);
        }

        $events = [
            ['Friday live music on the terrace', 'Local musicians, sundowners and small plates.', 'Terrace', $today->next(CarbonImmutable::FRIDAY)->setTime(19, 0), 3],
            ['Sunday family brunch', 'A relaxed brunch with dishes for all ages.', 'Restaurant', $today->next(CarbonImmutable::SUNDAY)->setTime(11, 0), 3],
        ];

        foreach ($events as [$title, $summary, $venue, $startsAt, $hours]) {
            HotelEvent::create(['title' => $title, 'summary' => $summary, 'venue' => $venue, 'starts_at' => $startsAt, 'ends_at' => $startsAt->addHours($hours), 'is_published' => true]);
        }

        $posts = [
            ['A first-timer’s guide to Monrovia', 'Where to walk, what to eat and how to get around.', "## Getting around\n\nTaxis and keke (tricycles) are easy to find; agree the fare before you set off. Our front desk can also arrange a car.\n\n## What to eat\n\nTry palm butter, potato greens and freshly grilled fish — all on our restaurant menu.", 5],
            ['Planning a business trip with us', 'Practical tips for working stays.', 'Every room has a desk and reliable Wi-Fi. Ask reception for printing, meeting space or an early breakfast before a morning flight.', 2],
        ];

        foreach ($posts as [$title, $excerpt, $body, $daysAgo]) {
            $post = new Post(['title' => $title, 'excerpt' => $excerpt, 'body' => $body, 'published_at' => now()->subDays($daysAgo)]);
            $post->author_id = $author?->id;
            $post->save();
        }

        $this->command->info('Website demo content seeded (no reviews: those come from real stays).');
    }
}
