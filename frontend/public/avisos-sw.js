self.addEventListener('push', (event) => {
  const aviso = event.data?.json();
  if (!aviso?.id) return;

  event.waitUntil(Promise.all([
    self.registration.showNotification('Casos Especiales', {
      body: 'Tienes una novedad en una solicitud. Abre la campana para consultarla.',
      tag: 'caso-especial-' + aviso.id,
    }),
    self.clients.matchAll({ type: 'window' }).then(clientes => {
      clientes.forEach(cliente => cliente.postMessage({ tipo: 'aviso-caso' }));
    }),
  ]));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const destino = new URL('estudiante?avisos=1', self.registration.scope).href;
  event.waitUntil(self.clients.openWindow(destino));
});
