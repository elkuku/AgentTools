---
layout: home

hero:
  name: AgentTools
  text: Tus estadísticas y medallas de Ingress en un solo lugar
  tagline: Comparte tus estadísticas de siempre (ALL TIME) desde Ingress con la app para Android. Las guarda, muestra qué cambió y conserva tus medallas, tu línea de tiempo y tus banners, en el teléfono y en la web.
  image:
    src: /logo.svg
    alt: AgentTools
  actions:
    - theme: brand
      text: Empezar
      link: /es/guide/
    - theme: alt
      text: Descargar la app
      link: /es/downloads
    - theme: alt
      text: Abrir el sitio web
      link: https://agent-tools.elkuku.dev

features:
  - icon: 📤
    title: Compartir desde Ingress
    details: Abre tu perfil de agente, elige ALL TIME y compártelo con AgentTools. Las estadísticas se guardan al instante.
    link: /es/guide/app#share-your-stats
  - icon: 📈
    title: Mira qué cambió
    details: Después de cada subida, cada estadística que subió, las medallas que alcanzaron un nuevo nivel y tu nuevo nivel de agente.
    link: /es/guide/app#see-what-changed
  - icon: 🏅
    title: Medallas y medallas especiales
    details: Tus medallas de bronce a ónix, y las medallas de anomalías, anuales y de eventos leídas de capturas de pantalla.
    link: /es/guide/app#custom-medals
  - icon: 🗓️
    title: Línea de tiempo
    details: Tus subidas, subidas de nivel y medallas mejoradas con tus propios eventos y viajes, con un gráfico de AP.
    link: /es/guide/app#timeline
  - icon: 🗺️
    title: Banners
    details: Sincroniza tus banners publicados, completados y pendientes desde Bannergress y míralos en un mapa.
    link: /es/guide/app#banners
  - icon: 👥
    title: Agentes
    details: Mira a los agentes que comparten su perfil y elige lo que otros ven del tuyo.
    link: /es/guide/privacy
---

<div class="screens">
  <img src="/screenshots/app/home.webp" alt="La pantalla de inicio de la app">
  <img src="/screenshots/app/my-stats.webp" alt="Mis estadísticas con medallas">
  <img src="/screenshots/app/import-result.webp" alt="Qué cambió después de una subida">
  <img src="/screenshots/app/timeline.webp" alt="La línea de tiempo">
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
