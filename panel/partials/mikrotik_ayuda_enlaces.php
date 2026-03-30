<?php
declare(strict_types=1);
/** Ayuda contextual: cómo alcanza NetControl la API RouterOS (CGNAT, túneles, IP alcanzable). */
?>
<div id="ayuda-enlaces-mikrotik" class="nc-card p-4 border-secondary border-opacity-25">
    <h2 class="h6 text-uppercase text-secondary mb-3">Cómo conectar un MikroTik nuevo</h2>
    <ol class="small mb-3 ps-3">
        <li class="mb-2"><strong>Red</strong>: el servidor donde corre PHP (este panel) debe poder abrir TCP hacia la IP/host y puerto API del router (típico <strong>8728</strong> o <strong>8729</strong> con TLS).</li>
        <li class="mb-2"><strong>RouterOS</strong>: <code>IP → Services</code> → API habilitado; usuario API con permisos; <code>Firewall Filter</code> que permita el puerto desde la IP del panel (o desde la red del túnel).</li>
        <li class="mb-2"><strong>Campo “IP o host”</strong> en NetControl = la dirección <strong>tal como la ve el PHP</strong>, no necesariamente la IP “WAN” del sitio.</li>
    </ol>

    <h3 class="h6 text-warning mb-2">Sitio remoto detrás de CGNAT (o sin IPv4 pública)</h3>
    <p class="small mb-2">Muchos enlaces residenciales, 4G/LTE o satélite salen por <strong>CGNAT</strong> o sin una IPv4 pública fija hacia el MikroTik. Si el router no es alcanzable desde el servidor del panel, PHP <strong>no puede</strong> abrir la API “por internet” solo con la IP que ves en el sitio.</p>
    <p class="small mb-2">Patrones habituales:</p>
    <ul class="small mb-3">
        <li><strong>Túnel VPN hacia tu núcleo</strong> (WireGuard, IPsec, SSTP, etc.): el MK remoto es <strong>cliente</strong> y recibe una IP en la VPN. En NetControl ponés esa <strong>IP del túnel</strong> (la que ve el servidor del panel o un router central).</li>
        <li><strong>Mesh/overlay</strong> (Tailscale, ZeroTier, etc.): misma idea — usá la IP virtual que el panel pueda rutear o que resuelva por nombre si aplicable.</li>
        <li><strong>VPN inversa / hub</strong>: tráfico entrante termina en un equipo en tu red; el MK se asocia a esa IP/puerto solo si configurás reenvío explícito (menos común para API).</li>
    </ul>

    <h3 class="h6 text-info mb-2">Patrón habitual (VPN hacia un NOC / proveedor upstream)</h3>
    <p class="small mb-2"><strong>Flujo típico:</strong> en el núcleo hay un <strong>servidor VPN</strong> (muchas veces OpenVPN: puerto 1194, TCP/UDP) en el NOC o la nube. Te pasan los datos de conexión (servidor público, usuario, contraseña, cifrado, perfil…). En el MikroTik creás un <strong>OVPN Client</strong>, pegás esa configuración en la pestaña <strong>Dial Out</strong> (Connect To, User, Password…). El router <strong>inicia salida</strong> hacia ellos; por eso el patrón sirve cuando el sitio no tiene IP pública o está detrás de CGNAT.</p>
    <p class="small mb-2">Cuando el túnel queda <strong>activo</strong>, el servidor asigna al MK una <strong>IP privada dentro de esa VPN</strong> (la ves en el <strong>panel de gestión</strong> que uses o en <code>IP → Addresses</code> sobre la interfaz del cliente VPN). En NetControl cargás esa misma dirección en <strong>Routers → IP o host</strong>, junto con el <strong>usuario/contraseña API</strong> del RouterOS. Orden lógico: <em>primero VPN estable</em> → <em>después</em> alta en el panel con la IP del túnel.</p>
    <p class="small mb-2">En <strong>software de gestión remota</strong> habitual, cada router aparece con la dirección que le da el <strong>servidor VPN</strong> del NOC. El panel no abre la API “hacia la WAN del cliente” si esa WAN no es enrutable; usa la <strong>IP interna del túnel</strong> (u overlay) que sí llega desde tu red.</p>
    <ul class="small mb-3">
        <li><strong>IP / host en NetControl</strong> = la dirección que el PHP debe usar para <strong>API RouterOS</strong> (suele ser la del túnel u overlay). Si además usás el hub WireGuard de este repo, esa otra VPN usa <code>10.64.0.x</code> — no mezcles direcciones de una VPN con la de la otra.</li>
        <li><strong>Usuario / contraseña API</strong> = igual lógica: usuario API en RouterOS con políticas <code>read,write,api</code> (o grupo equivalente).</li>
        <li><strong>RADIUS</strong>: algunos paneles muestran NAS IP y secreto hacia tu <strong>FreeRADIUS</strong> u otro AAA. <strong>NetControl</strong>, en esta versión, <strong>no integra servidor RADIUS</strong>; PPPoE/colores de ancho se gestionan vía <strong>API</strong> (<code>/ppp/secret</code>, colas simples). Si necesitás RADIUS + panel, se suele sumar un stack aparte o desarrollo a medida.</li>
        <li><strong>Colas</strong>: otros sistemas distinguen colas “simples estáticas” vs “dinámicas”; NetControl hoy trabaja con <strong>colas simples</strong> y comentario <code>NetControl-{id}</code> para atar cliente ↔ cola (revisá <code>funciones.php</code> si extendés el modelo).</li>
    </ul>

    <h3 class="h6 text-success mb-2">Hub VPN propio (VM + MikroTik borde) — uso y lógica</h3>
    <p class="small mb-2">En este repo, la carpeta <code>deploy/vpn-hub/</code> prepara una <strong>VM Ubuntu</strong> como servidor <strong>WireGuard</strong>: red túnel <strong><code>10.64.0.0/24</code></strong>, servidor en <strong><code>10.64.0.1</code></strong>, cada MikroTik remoto recibe <strong><code>10.64.0.x</code></strong> (rango propio; usalo solo para este hub). Esa misma IP es la que cargás en NetControl en <strong>Routers → IP o host</strong> para la API de esos enlaces.</p>
    <p class="small mb-2"><strong>Flujo lógico (quién habla con quién):</strong></p>
    <ol class="small mb-3 ps-3">
        <li class="mb-2">El <strong>MikroTik remoto</strong> (típicamente sin IPv4 pública o detrás de CGNAT) <strong>inicia</strong> UDP hacia tu <strong>IP o DDNS públicos</strong> + puerto <strong>51820</strong> (WireGuard). El sitio no necesita IP pública entrante.</li>
        <li class="mb-2">Tu <strong>MikroTik de borde</strong> (el que tiene la salida a internet) recibe ese tráfico en la WAN y, con <strong>NAT dst-nat</strong> (reenvío de puerto), lo envía a la <strong>IP LAN de la VM hub</strong> (ej. <code>192.168.1.50:51820</code> — ajustá a tu red).</li>
        <li class="mb-2">La <strong>VM hub</strong> termina el túnel WireGuard y asigna al cliente la IP <strong><code>10.64.0.x</code></strong>.</li>
        <li class="mb-2">El servidor <strong>NetControl</strong> (PHP) debe alcanzar <strong>TCP 8728</strong> (API) hacia <strong><code>10.64.0.x</code></strong>: o bien hay ruta desde la LAN del panel hasta <code>10.64.0.0/24</code>, o el panel corre en la misma red que el hub y resuelve la ruta en el MikroTik central.</li>
    </ol>
    <p class="small mb-2"><strong>Qué configurar en el MikroTik borde (resumen):</strong></p>
    <ul class="small mb-3">
        <li><strong>IP → Firewall → NAT → +</strong>: cadena <code>dstnat</code>, protocolo <code>udp</code>, puerto destino <strong>51820</strong>, in-interface la <strong>WAN</strong>, acción <code>dst-nat</code> a <strong>la IP LAN de la VM hub</strong> y puerto <strong>51820</strong> (comentario: <code>WireGuard hub NetControl</code>).</li>
        <li><strong>IP → DHCP Server → Leases</strong> (si la VM usa DHCP o querés reservar): MAC de la VM <code>ens18</code> → IP fija en tu LAN; si la IP es estática en Ubuntu, igual podés reservar para evitar choques con el pool.</li>
        <li><strong>Firewall filter</strong>: no bloquear UDP 51820 donde corresponda; la VM hub no expone otros puertos obligatorios para WireGuard.</li>
        <li>Scripts en GitHub: <code>prepare-wireguard-hub.sh</code> y <code>wg-server-add-peer.sh</code> (rama <code>main</code>, ruta <code>deploy/vpn-hub/</code>). Guía larga: <code>deploy/vpn-hub/INSTALAR-VPN-HUB.txt</code>.</li>
    </ul>

    <h3 class="h6 mb-2">Valores de “Tipo de enlace”</h3>
    <dl class="small mb-0">
        <dt class="mb-1">Directo</dt>
        <dd class="mb-2">LAN del mismo edificio, IP pública fija, o cualquier caso en que TCP al host:puerto llegue sin VPN de por medio.</dd>
        <dt class="mb-1">Túnel / VPN</dt>
        <dd class="mb-2">La IP guardada es la del enlace (WireGuard, IPsec, etc.). Indicá en notas el perfil o el nodo central.</dd>
        <dt class="mb-1">CGNAT / sin IP pública</dt>
        <dd class="mb-0">Marcá el escenario; la IP útil sigue siendo la del <strong>túnel u overlay</strong> que el panel puede alcanzar, mientras la WAN del sitio no sea enrutable hacia el API.</dd>
    </dl>
</div>
