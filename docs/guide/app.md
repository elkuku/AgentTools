# The Android app

The app receives the stats you share from Ingress, saves them to your account and shows them with
your medals. Everything it shows is also on the [website](/guide/web).

## Home

<img src="/screenshots/app/home.webp" alt="Home screen" class="shot-phone right">

After signing in, the home screen sums up your account:

- **My stats**: your agent, level, AP, the date of your latest upload and the number of uploads
  and custom medals. **Open** shows them in full.
- **My banners**: how many banners you published, did and have on your to-do list.
- **Your profile**: what other users see of you. **Change** opens the settings.
- **Agents**: the agents who show their profile.
- **Share from Ingress**: a reminder of how to upload.

At the top, your profile picture opens the account menu (your email and role, **My stats**,
**My banners**, **Settings**, **Sign out**), and ☰ opens the feature menu with **Agents**. Pull
down on a screen to reload it.

<div style="clear: both"></div>

## Share your stats

1. In Ingress, open your agent profile and select **ALL TIME**.
2. Tap the share button and choose **AgentTools**.

The stats are saved right away, even when the app wasn't open. If you aren't signed in yet, they
wait until you are. The same stats can't be saved twice, and stats of another agent are refused.

### See what changed

<img src="/screenshots/app/import-result.webp" alt="What changed after an upload" class="shot-phone right">

After each upload the app shows what changed since the one before:

- **Medal upgrades**: the medals that reached a new tier. Onyx medals count again each time the
  stat reaches the onyx value once more (×2, ×3, …).
- **AP** gained.
- Every stat that went up, with the medal it counts for and how much is missing for the next
  tier (the negative number).
- Your new level, when you reached one.

**Close** hides it; the stats stay saved.

<div style="clear: both"></div>

## My stats

<div class="shots">
  <img src="/screenshots/app/my-stats.webp" alt="My stats with medals" class="shot-phone">
  <img src="/screenshots/app/medal-details.webp" alt="A medal's details" class="shot-phone">
  <img src="/screenshots/app/my-stats-medals.webp" alt="Custom medals and summaries" class="shot-phone">
</div>

**My stats** shows your newest upload the way Ingress does:

- your agent name with faction logo and level, and your C.O.R.E subscription months;
- your **AP** and every medal with the tier your stats reach, from bronze to onyx. Tap a medal
  for the stat behind it and the values of each tier;
- **Dronenet** and **Kinetics** stats, and the date of your first and latest upload;
- your **custom medals** (anomaly, annual and event);
- a summary of your **banners** and your **timeline** (uploads, events, travels and banners). Tap
  them to open those screens.

## Custom medals

Anomaly, annual and event medals aren't in the stats Ingress shares, so the app reads them from
screenshots of your medal grid:

1. In Ingress, open your agent profile and take screenshots of your medals: the top, the middle and
   the bottom, so every row is on at least one of them.
2. Select all of them in your gallery app and share them with **AgentTools**.
3. Check the medals the app found. Untick wrong ones, and tick the faded ones (the app wasn't sure)
   that are right.
4. Save. The medals are added to the ones you already have, keeping the higher tier.

The screenshots are read on your phone; only the medals you save are uploaded.

## Timeline

<img src="/screenshots/app/timeline.webp" alt="The timeline" class="shot-phone right">

The timeline (My stats → **Timeline**) tells your Ingress story, newest first:

- your **uploads** with what changed: the first upload, new levels, recursions and medal
  upgrades (uploads where only the AP changed are left out);
- your own **events**, e.g. an anomaly or a mission day, and **travels**: events over several days,
  with a line beside the entries during the trip;
- the **banners** you did, on the day you did them.

The chart above it shows your AP over time, with a flag for each event and a band for each travel.
Tap a mark for its details.

The filters below the chart show or hide medal upgrades, new levels, events, travels and banners.
**All dates** picks a date range; the chart zooms to it.

**Add event** adds an event with its day and text. Give it an end date to make it a travel. Travels
can't overlap. Tap an event to change or delete it.

<div style="clear: both"></div>

## Banners

<img src="/screenshots/app/banners.webp" alt="My banners with the map" class="shot-phone right">

**My banners** shows your banners from [Bannergress](https://bannergress.com):

1. Tap **Sign in to Bannergress** and sign in in the page that opens.
2. The app syncs the banners you **published** (with a mission by your agent), your whole **done**
   list and your **to-do** list, and saves them to your account.

The map has a dot where each banner starts: done, to-do and published in their own colors. The
buttons below the map show or hide each list. Below are the lists in the same order, the done one
open; tap a heading to fold or open one.

Tap a done banner to set the day you did it: then it's on your timeline. Event banners get the
event's first day when they are synced.

Sync again whenever your lists change. Signing out of Google also signs out of Bannergress.

<div style="clear: both"></div>

## Agents

<div class="shots">
  <img src="/screenshots/app/agents.webp" alt="The agents list" class="shot-phone">
  <img src="/screenshots/app/agent-profile.webp" alt="An agent's profile" class="shot-phone">
</div>

☰ → **Agents** lists the agents who show their profile, with faction and level. Tap one to see
the parts of their profile they chose to show: their latest stats with medals, their custom
medals, their stats history and their banners. As on **My stats**, their timeline and their
banners are summed up there: tap **Timeline** or **Banners** to open them on a screen of their own.

Admins also have ☰ → **Admin agents**: every user with what they show.

## Settings

<img src="/screenshots/app/settings.webp" alt="Settings" class="shot-phone right">

The settings (account menu → **Settings**) have:

- your **agent name**, set by your first upload;
- **Visible to other users**: the parts of your profile other signed-in users see. See
  [Privacy](/guide/privacy);
- the **language**: the phone's language, English, German or Spanish;
- the app's version.

The dev and debug builds can also switch the server there.

<div style="clear: both"></div>
