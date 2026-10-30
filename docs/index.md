---
layout: home

hero:
  name: AgentTools
  text: Your Ingress stats and medals in one place
  tagline: Share your all-time stats from Ingress with the Android app. It saves them, shows what changed and keeps your medals, timeline and banners, on your phone and on the web.
  image:
    src: /logo.svg
    alt: AgentTools
  actions:
    - theme: brand
      text: Get started
      link: /guide/
    - theme: alt
      text: Download the app
      link: /downloads
    - theme: alt
      text: Open the website
      link: https://agent-tools.elkuku.dev

features:
  - icon: 📤
    title: Share from Ingress
    details: Open your agent profile, select ALL TIME and share it with AgentTools. The stats are saved right away.
    link: /guide/app#share-your-stats
  - icon: 📈
    title: See what changed
    details: After each upload, every stat that went up, the medals that reached a new tier and your new level.
    link: /guide/app#see-what-changed
  - icon: 🏅
    title: Medals and custom medals
    details: Your medals from bronze to onyx, and anomaly, annual and event medals read from screenshots.
    link: /guide/app#custom-medals
  - icon: 🗓️
    title: Timeline
    details: Your uploads, level-ups and medal upgrades with your own events and travels, with an AP chart.
    link: /guide/app#timeline
  - icon: 🗺️
    title: Banners
    details: Sync your published, done and to-do banners from Bannergress and see them on a map.
    link: /guide/app#banners
  - icon: 👥
    title: Agents
    details: See the agents who share their profile, and choose what others see of yours.
    link: /guide/privacy
---

<div class="screens">
  <img src="/screenshots/app/home.webp" alt="The app's home screen">
  <img src="/screenshots/app/my-stats.webp" alt="My stats with medals">
  <img src="/screenshots/app/import-result.webp" alt="What changed after an upload">
  <img src="/screenshots/app/timeline.webp" alt="The timeline">
</div>

<style>
.screens {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  max-width: 1152px;
  margin: 48px auto 0;
  padding: 0 24px;
}
.screens img {
  border-radius: 12px;
  border: 1px solid var(--vp-c-divider);
}
@media (max-width: 768px) {
  .screens { grid-template-columns: repeat(2, 1fr); }
}
</style>
