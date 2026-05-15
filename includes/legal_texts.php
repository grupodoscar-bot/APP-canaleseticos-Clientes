<?php
declare(strict_types=1);
// Textos legales ES/EN. Devueltos como HTML (ya escapado donde corresponde).

function legal_policy_es(): string { return <<<'H'
<p class="small muted">Última revisión: %%DATE%% · Titular: <strong>%%EMP%%</strong></p>

<h2 id="s1">Finalidad</h2>
<p>Este canal interno, gestionado por <strong>%%EMP%%</strong>, permite comunicar acciones u omisiones que puedan constituir infracciones del Derecho de la Unión Europea o infracciones graves o muy graves del ordenamiento jurídico español, conforme a la <strong>Ley 2/2023, de 20 de febrero</strong> y la <strong>Directiva (UE) 2019/1937</strong>.</p>

<h2 id="s2">Personas que pueden informar</h2>
<p>Empleados, ex-empleados, personas en procesos de selección, personas en prácticas, voluntarios, autónomos, accionistas, miembros de órganos de administración, y cualquier persona que trabaje o haya trabajado para la entidad o para contratistas, subcontratistas o proveedores.</p>

<h2 id="s3">Información que puede comunicarse</h2>
<p>Este canal está unificado conforme a la Ley 2/2023 y otras normas de compliance:</p>
<ul>
    <li>Fraude financiero, corrupción o soborno.</li>
    <li><strong>Acoso laboral o mobbing.</strong></li>
    <li><strong>Acoso sexual y por razón de sexo</strong> (LO 10/2022 · LO 3/2007).</li>
    <li><strong>Discriminación LGTBI</strong> (Ley 4/2023).</li>
    <li><strong>Otras formas de discriminación</strong> (Ley 15/2022).</li>
    <li><strong>Violencia contra menores</strong> (LO 8/2021 · LOPIVI).</li>
    <li>Infracciones de protección de datos.</li>
    <li>Blanqueo de capitales y financiación del terrorismo.</li>
    <li>Salud, seguridad laboral o medio ambiente.</li>
    <li>Conflictos de interés o contratación pública irregular.</li>
    <li>Competencia desleal.</li>
    <li>Cualquier otra infracción grave o muy grave del ordenamiento.</li>
</ul>

<h2 id="s4">Canales y formato</h2>
<ul>
    <li><strong>Escrita y anónima</strong> vía web: <a href="/denunciar.php?modo=anonima">formulario anónimo</a>. No se solicita ningún dato identificativo; se emite un código único de seguimiento de 12 caracteres para consultar el estado y comunicarse con el gestor.</li>
    <li><strong>Escrita e identificada</strong> vía web: <a href="/registro.php">registro</a> / <a href="/login.php">acceso</a>. El informante crea una cuenta con email y contraseña y accede a un panel privado con todas sus denuncias, su estado y la conversación con el gestor. La identidad se trata con la misma confidencialidad reforzada (art. 32 Ley 2/2023) y solo es accesible al Responsable del Sistema.</li>
    %%TEL_LINE%%
    <li><strong>Presencial o verbal</strong>: <a href="/reunion.php">solicitar reunión</a>, concertada en un plazo máximo de <strong>7 días naturales</strong> (art. 9.2 Directiva UE).</li>
</ul>

<h2 id="s5">Garantías del informante</h2>
<ul>
    <li><strong>Confidencialidad.</strong> Identidad y contenido protegidos.</li>
    <li><strong>Anonimato.</strong> No es necesario identificarse.</li>
    <li><strong>Protección frente a represalias.</strong> Prohibida toda forma de represalia.</li>
    <li><strong>Cifrado.</strong> AES-256-GCM en la base de datos.</li>
</ul>

<h2 id="s6">Tramitación y plazos</h2>
<ul>
    <li>Acuse de recibo: <strong>7 días naturales</strong>.</li>
    <li>Resolución: <strong>3 meses</strong> (ampliable a 6 en casos complejos).</li>
    <li>Comunicación bidireccional por código de seguimiento.</li>
</ul>

<h2 id="s7">Responsable del Sistema</h2>
<p>Designado por %%EMP%% con independencia y autonomía en sus funciones.</p>

<h2 id="s8">Comunicaciones falsas</h2>
<p>Las denuncias deliberadamente falsas pueden dar lugar a responsabilidades según la <strong>LO 1/1982</strong> y los <strong>arts. 205-216 del Código Penal</strong>. Las de buena fe no acreditadas no generan responsabilidad.</p>

<h2 id="s9">Conservación</h2>
<p>Datos personales: máximo <strong>3 meses</strong> desde la recepción salvo investigación en curso. Libro-registro (art. 26 Ley 2/2023): <strong>10 años</strong>, metadatos sin datos personales.</p>

<h2 id="s10">Canal externo · A.A.I.</h2>
<p>Puedes informar directamente a la <strong>Autoridad Independiente de Protección del Informante</strong> (RD 1101/2024, operativa desde 1-sep-2025). No es necesario agotar el canal interno.</p>
<p>Canal externo: <a href="https://www.proteccioninformante.gob.es" target="_blank" rel="noopener">proteccioninformante.gob.es</a></p>

<h2 id="s11">Contacto</h2>
<p>Responsable del Sistema: <a href="mailto:%%COMPL%%">%%COMPL%%</a>%%DPO_LINE%%</p>
H;
}

function legal_policy_en(): string { return <<<'H'
<p class="small muted">Last revised: %%DATE%% · Data controller: <strong>%%EMP%%</strong></p>

<h2 id="s1">Purpose</h2>
<p>This internal channel, managed by <strong>%%EMP%%</strong>, allows reporting actions or omissions that may constitute breaches of EU law or serious infringements of Spanish law, pursuant to <strong>Law 2/2023</strong> and <strong>Directive (EU) 2019/1937</strong>.</p>

<h2 id="s2">Who may report</h2>
<p>Employees, former employees, job applicants, trainees, volunteers, self-employed workers, shareholders, members of governing bodies, and anyone working or having worked for the entity or for its contractors, subcontractors or suppliers.</p>

<h2 id="s3">Information that may be reported</h2>
<p>This channel is unified under Law 2/2023 and complementary compliance rules:</p>
<ul>
    <li>Financial fraud, corruption or bribery.</li>
    <li><strong>Workplace harassment / mobbing.</strong></li>
    <li><strong>Sexual or sex-based harassment</strong> (OL 10/2022 · OL 3/2007).</li>
    <li><strong>LGTBI discrimination</strong> (Law 4/2023).</li>
    <li><strong>Other discrimination</strong> (Law 15/2022).</li>
    <li><strong>Violence against minors</strong> (OL 8/2021 · LOPIVI).</li>
    <li>Data protection infringements.</li>
    <li>Money laundering and terrorism financing.</li>
    <li>Health, safety or environmental issues.</li>
    <li>Conflicts of interest or public procurement irregularities.</li>
    <li>Unfair competition.</li>
    <li>Any other serious breach of Spanish or EU law.</li>
</ul>

<h2 id="s4">Channels and format</h2>
<ul>
    <li><strong>Written, anonymous</strong> via web: <a href="/denunciar.php?modo=anonima">anonymous form</a>. No identifying data is requested; a unique 12-character tracking code is issued to check status and chat with the case manager.</li>
    <li><strong>Written, identified</strong> via web: <a href="/registro.php">sign up</a> / <a href="/login.php">sign in</a>. The reporter creates an account with email and password and accesses a private dashboard with all their reports, their status and the conversation with the case manager. The identity is protected with the same reinforced confidentiality (Law 2/2023 art. 32) and only accessible to the System Manager.</li>
    %%TEL_LINE%%
    <li><strong>In person or verbal</strong>: <a href="/reunion.php">request a meeting</a>, to be held within a maximum of <strong>7 calendar days</strong> (art. 9.2 EU Directive).</li>
</ul>

<h2 id="s5">Reporter guarantees</h2>
<ul>
    <li><strong>Confidentiality.</strong> Identity and content protected.</li>
    <li><strong>Anonymity.</strong> No need to identify yourself.</li>
    <li><strong>Protection from retaliation.</strong> All forms of retaliation prohibited.</li>
    <li><strong>Encryption.</strong> AES-256-GCM at rest in the database.</li>
</ul>

<h2 id="s6">Processing and deadlines</h2>
<ul>
    <li>Acknowledgement of receipt: <strong>7 calendar days</strong>.</li>
    <li>Resolution: <strong>3 months</strong> (extendable to 6 in complex cases).</li>
    <li>Two-way communication via tracking code.</li>
</ul>

<h2 id="s7">System Manager</h2>
<p>Appointed by %%EMP%% with independence and autonomy in their duties.</p>

<h2 id="s8">False reports</h2>
<p>Deliberately false reports may trigger liability under <strong>OL 1/1982</strong> and <strong>arts. 205-216 of the Criminal Code</strong>. Good-faith reports that are ultimately unproven do not generate liability.</p>

<h2 id="s9">Retention</h2>
<p>Personal data: maximum <strong>3 months</strong> from receipt unless investigation is ongoing. Record book (art. 26 Law 2/2023): <strong>10 years</strong>, metadata without personal data of the reporter.</p>

<h2 id="s10">External channel · A.A.I.</h2>
<p>You may also report directly to the <strong>Independent Whistleblower Protection Authority</strong> (RD 1101/2024, operational since 1-Sep-2025). The internal channel does not need to be exhausted first.</p>
<p>External channel: <a href="https://www.proteccioninformante.gob.es" target="_blank" rel="noopener">proteccioninformante.gob.es</a></p>

<h2 id="s11">Contact</h2>
<p>System Manager: <a href="mailto:%%COMPL%%">%%COMPL%%</a>%%DPO_LINE%%</p>
H;
}

function legal_privacy_es(): string { return <<<'H'
<h2 id="s1">Responsable del tratamiento</h2>
<p><strong>%%EMP%%</strong>. Responsable del Sistema: <a href="mailto:%%COMPL%%">%%COMPL%%</a>.</p>
%%DPO_BLOCK%%

<h2 id="s2">Finalidad</h2>
<p>Gestión de comunicaciones al canal interno, investigación de los hechos y, en su caso, medidas disciplinarias, judiciales o regulatorias.</p>

<h2 id="s3">Base jurídica</h2>
<p>Obligación legal (art. 6.1.c RGPD) en relación con la Ley 2/2023 y la Directiva (UE) 2019/1937. No se solicita consentimiento.</p>

<h2 id="s4">Categorías de datos</h2>
<p>Los datos se facilitan voluntariamente. El canal permite dos modalidades:</p>
<ul>
    <li><strong>Denuncia anónima</strong>: no se solicita ningún dato identificativo. Solo se tratan los datos estrictamente necesarios sobre los hechos.</li>
    <li><strong>Denuncia identificada</strong>: requiere crear una cuenta con <em>email</em>, <em>contraseña cifrada</em> (bcrypt con coste 12) y opcionalmente <em>nombre</em>. El nombre se almacena cifrado con AES-256-GCM. El email es necesario como identificador de cuenta y no se cede a terceros. Puedes solicitar la supresión de tu cuenta en cualquier momento.</li>
</ul>
<p>Adjuntos: se almacenan cifrados con AES-256-GCM y metadatos (nombre, tipo MIME, tamaño, hash) registrados. Comunicaciones del chat: cifradas en reposo.</p>

<h2 id="s5">Destinatarios</h2>
<p>Responsable del Sistema y personas autorizadas. Sin cesiones a terceros salvo obligación legal (autoridad judicial, Ministerio Fiscal, A.A.I., autoridades competentes).</p>

<h2 id="s6">Transferencias internacionales</h2>
<p>No se realizan transferencias fuera del Espacio Económico Europeo.</p>

<h2 id="s7">Plazo de conservación</h2>
<p>Datos personales: máximo <strong>3 meses</strong> (art. 32.2 Ley 2/2023) salvo investigación. Libro-registro (art. 26): <strong>10 años</strong> con metadatos sin datos personales, accesible solo a autoridad judicial.</p>

<h2 id="s8">Medidas de seguridad</h2>
<ul>
    <li>Cifrado AES-256-GCM en reposo.</li>
    <li>TLS en tránsito.</li>
    <li>2FA TOTP y registro de auditoría con hash encadenado.</li>
    <li>No se registra la dirección IP; los identificadores internos se hashean con clave secreta.</li>
</ul>

<h2 id="s9">Derechos</h2>
<p>Puedes ejercer acceso, rectificación, supresión, oposición, limitación y portabilidad en <a href="mailto:%%DERECHOS%%">%%DERECHOS%%</a>. Reclamación ante la <strong>AEPD</strong> (<a href="https://www.aepd.es" target="_blank" rel="noopener">aepd.es</a>) y la <strong>A.A.I.</strong></p>
<p>Ante incidentes de seguridad con riesgo para tus datos, se notificará a la AEPD en 72 h (art. 33 RGPD).</p>

<h2 id="s10">Decisiones automatizadas</h2>
<p>No se adoptan decisiones basadas únicamente en tratamientos automatizados ni elaboración de perfiles.</p>
H;
}

function legal_privacy_en(): string { return <<<'H'
<h2 id="s1">Data controller</h2>
<p><strong>%%EMP%%</strong>. System Manager: <a href="mailto:%%COMPL%%">%%COMPL%%</a>.</p>
%%DPO_BLOCK%%

<h2 id="s2">Purpose</h2>
<p>Management of internal channel reports, investigation of facts and, where appropriate, disciplinary, judicial or regulatory measures.</p>

<h2 id="s3">Legal basis</h2>
<p>Legal obligation (art. 6.1.c GDPR) under Law 2/2023 and Directive (EU) 2019/1937. Consent is not requested.</p>

<h2 id="s4">Categories of data</h2>
<p>Data is provided voluntarily. The channel offers two modes:</p>
<ul>
    <li><strong>Anonymous report</strong>: no identifying data is requested. Only data strictly necessary about the facts is processed.</li>
    <li><strong>Identified report</strong>: requires creating an account with <em>email</em>, a <em>hashed password</em> (bcrypt cost 12) and optionally <em>full name</em>. The name is stored encrypted with AES-256-GCM. The email is required as account identifier and is not shared with third parties. You may request deletion of your account at any time.</li>
</ul>
<p>Attachments are stored encrypted with AES-256-GCM with metadata recorded (name, MIME type, size, hash). Chat communications are encrypted at rest.</p>

<h2 id="s5">Recipients</h2>
<p>System Manager and authorised personnel. No transfers to third parties unless legally required (judicial authority, Prosecutor, A.A.I., competent authorities).</p>

<h2 id="s6">International transfers</h2>
<p>No transfers outside the European Economic Area.</p>

<h2 id="s7">Retention period</h2>
<p>Personal data: maximum <strong>3 months</strong> (art. 32.2 Law 2/2023) unless investigation is ongoing. Record book (art. 26): <strong>10 years</strong> with metadata and no personal data, accessible only to judicial authority.</p>

<h2 id="s8">Security measures</h2>
<ul>
    <li>AES-256-GCM encryption at rest.</li>
    <li>TLS in transit.</li>
    <li>TOTP 2FA and tamper-evident audit log (hash-chain).</li>
    <li>IP addresses are not logged; internal identifiers are hashed with a secret key.</li>
</ul>

<h2 id="s9">Rights</h2>
<p>You may exercise access, rectification, erasure, objection, restriction and portability at <a href="mailto:%%DERECHOS%%">%%DERECHOS%%</a>. You may also lodge a complaint with the Spanish Data Protection Agency (<strong>AEPD</strong>, <a href="https://www.aepd.es" target="_blank" rel="noopener">aepd.es</a>) or the <strong>A.A.I.</strong></p>
<p>Security incidents affecting your data will be notified to the AEPD within 72 hours (art. 33 GDPR).</p>

<h2 id="s10">Automated decisions</h2>
<p>No decisions are made solely on automated processing, nor is profiling carried out.</p>
H;
}

function legal_notice_es(): string { return <<<'H'
<h2 id="s1">Titularidad</h2>
<p>El sitio <code>%%HOST%%</code> es titularidad de <strong>%%EMP%%</strong>, destinado exclusivamente a la recepción de comunicaciones al canal interno de información.</p>

<h2 id="s2">Contacto</h2>
<p>Correo: <a href="mailto:%%COMPL%%">%%COMPL%%</a></p>

<h2 id="s3">Condiciones de uso</h2>
<p>El uso del canal queda sujeto a la política y al aviso de privacidad. El usuario se compromete a un uso diligente, veraz y conforme a la ley. Queda prohibido cualquier uso fraudulento o contrario a derechos de terceros.</p>

<h2 id="s4">Propiedad intelectual</h2>
<p>Los contenidos, salvo los aportados por usuarios, son propiedad de %%EMP%% o sus licenciantes. Prohibida su reproducción, distribución o transformación sin autorización.</p>

<h2 id="s5">Responsabilidad</h2>
<p>%%EMP%% no se hace responsable del contenido aportado por los usuarios. No se garantiza la disponibilidad continua ante fuerza mayor o mantenimiento.</p>

<h2 id="s6">Legislación aplicable</h2>
<p>Legislación española. Sometimiento a los Juzgados y Tribunales del domicilio del titular, salvo disposición legal distinta.</p>
H;
}

function legal_notice_en(): string { return <<<'H'
<h2 id="s1">Ownership</h2>
<p>The site <code>%%HOST%%</code> is owned by <strong>%%EMP%%</strong>, intended exclusively for the reception of reports submitted through the internal information channel.</p>

<h2 id="s2">Contact</h2>
<p>Email: <a href="mailto:%%COMPL%%">%%COMPL%%</a></p>

<h2 id="s3">Terms of use</h2>
<p>Use of the channel is subject to the policy and privacy notice. Users undertake to use it diligently, truthfully and lawfully. Any fraudulent or rights-infringing use is prohibited.</p>

<h2 id="s4">Intellectual property</h2>
<p>All content, except that provided by users, is property of %%EMP%% or its licensors. Reproduction, distribution or transformation without authorisation is prohibited.</p>

<h2 id="s5">Liability</h2>
<p>%%EMP%% is not liable for content submitted by users. Continuous availability is not guaranteed in cases of force majeure or maintenance.</p>

<h2 id="s6">Applicable law</h2>
<p>Spanish law. Jurisdiction: Courts of the controller's domicile, unless otherwise provided by law.</p>
H;
}

function legal_accessibility_es(): string { return <<<'H'
<p class="small muted">Última revisión: %%DATE%%</p>

<h2 id="s1">Compromiso</h2>
<p><strong>%%EMP%%</strong> se compromete a hacer accesible este sitio conforme al <strong>RD 193/2023</strong>, <strong>RD 1112/2018</strong>, <strong>UNE-EN 301549:2022</strong> y <strong>WCAG 2.1 AA</strong>.</p>

<h2 id="s2">Alcance</h2>
<p>Aplica al sitio <code>%%HOST%%</code> en su totalidad (páginas públicas y legales).</p>

<h2 id="s3">Estado de cumplimiento</h2>
<p>El sitio es <strong>parcialmente conforme</strong>. Medidas implementadas:</p>
<ul>
    <li>Etiquetado semántico y estructura jerárquica.</li>
    <li>Contraste AA.</li>
    <li>Navegación por teclado completa.</li>
    <li>Formularios con etiquetas asociadas.</li>
    <li>Textos alternativos en imágenes informativas.</li>
    <li>Funcionamiento básico sin JavaScript.</li>
    <li>Zoom 200% sin pérdida de contenido.</li>
    <li>Gestión de foco visible.</li>
</ul>

<h2 id="s4">Pendiente</h2>
<ul>
    <li>Auditoría formal con lectores de pantalla (JAWS, NVDA, VoiceOver).</li>
    <li>Validación exhaustiva con axe / Lighthouse / WAVE.</li>
    <li>Subtítulos y transcripciones en contenido multimedia futuro.</li>
</ul>

<h2 id="s5">Alternativas accesibles</h2>
<ul>
    <li>Email: <a href="mailto:%%COMPL%%">%%COMPL%%</a></li>
    %%TEL_LINE%%
    <li><a href="/reunion.php">Solicitar reunión presencial o verbal</a></li>
</ul>

<h2 id="s6">Quejas y sugerencias</h2>
<p><a href="mailto:%%COMPL%%?subject=Accesibilidad">%%COMPL%%</a>. Respuesta en <strong>20 días hábiles</strong> (RD 1112/2018). Reclamación final: <strong>Oficina de Atención a la Discapacidad</strong> (oadis@mdsocialesa2030.gob.es).</p>
H;
}

function legal_accessibility_en(): string { return <<<'H'
<p class="small muted">Last revised: %%DATE%%</p>

<h2 id="s1">Commitment</h2>
<p><strong>%%EMP%%</strong> is committed to making this site accessible in accordance with <strong>Royal Decree 193/2023</strong>, <strong>RD 1112/2018</strong>, <strong>UNE-EN 301549:2022</strong> and <strong>WCAG 2.1 AA</strong>.</p>

<h2 id="s2">Scope</h2>
<p>Applies to the site <code>%%HOST%%</code> in its entirety (public and legal pages).</p>

<h2 id="s3">Compliance status</h2>
<p>The site is <strong>partially compliant</strong>. Measures implemented:</p>
<ul>
    <li>Semantic markup and heading hierarchy.</li>
    <li>AA colour contrast.</li>
    <li>Full keyboard navigation.</li>
    <li>Forms with associated labels.</li>
    <li>Alt text on informative images.</li>
    <li>Basic operation without JavaScript.</li>
    <li>200% zoom without content loss.</li>
    <li>Visible focus management.</li>
</ul>

<h2 id="s4">Pending work</h2>
<ul>
    <li>Formal audit with screen readers (JAWS, NVDA, VoiceOver).</li>
    <li>Comprehensive validation via axe / Lighthouse / WAVE.</li>
    <li>Captions and transcripts for any future multimedia content.</li>
</ul>

<h2 id="s5">Accessible alternatives</h2>
<ul>
    <li>Email: <a href="mailto:%%COMPL%%">%%COMPL%%</a></li>
    %%TEL_LINE%%
    <li><a href="/reunion.php">Request an in-person or verbal meeting</a></li>
</ul>

<h2 id="s6">Complaints</h2>
<p><a href="mailto:%%COMPL%%?subject=Accessibility">%%COMPL%%</a>. Response within <strong>20 business days</strong> (RD 1112/2018). Final complaints: <strong>Office for Attention to Disability</strong> (oadis@mdsocialesa2030.gob.es).</p>
H;
}

function legal_cookies_es(): string { return <<<'H'
<h2 id="s1">Cookies utilizadas</h2>
<p>Este sitio usa <strong>exclusivamente una cookie técnica de sesión</strong> estrictamente necesaria. Exenta del deber de información y consentimiento del art. 22.2 LSSI-CE.</p>

<h2 id="s2">Detalle técnico</h2>
<table>
<thead><tr><th>Nombre</th><th>Finalidad</th><th>Duración</th><th>Tipo</th></tr></thead>
<tbody><tr><td><code>CANALSID</code></td><td>Identificador de sesión · mantener login y código de seguimiento.</td><td>Sesión</td><td>Técnica · propia · HttpOnly · SameSite=Lax</td></tr></tbody>
</table>

<h2 id="s3">Cookies NO utilizadas</h2>
<ul>
    <li>Analítica / tracking</li>
    <li>Publicidad</li>
    <li>Redes sociales</li>
    <li>Terceros</li>
</ul>
<p>Por ello no mostramos banner de cookies.</p>

<h2 id="s4">Almacenamiento local</h2>
<p>No se utiliza localStorage, sessionStorage ni IndexedDB.</p>

<h2 id="s5">Modificaciones</h2>
<p>Si se añadieran cookies no técnicas en el futuro, se actualizaría esta política y se solicitaría consentimiento previo.</p>
H;
}

function legal_cookies_en(): string { return <<<'H'
<h2 id="s1">Cookies used</h2>
<p>This site uses <strong>exclusively one strictly necessary session cookie</strong>. Exempt from information and consent requirements under art. 22.2 LSSI-CE.</p>

<h2 id="s2">Technical detail</h2>
<table>
<thead><tr><th>Name</th><th>Purpose</th><th>Duration</th><th>Type</th></tr></thead>
<tbody><tr><td><code>CANALSID</code></td><td>Session identifier · keeps login and tracking code.</td><td>Session</td><td>Technical · first-party · HttpOnly · SameSite=Lax</td></tr></tbody>
</table>

<h2 id="s3">Cookies NOT used</h2>
<ul>
    <li>Analytics / tracking</li>
    <li>Advertising</li>
    <li>Social networks</li>
    <li>Third parties</li>
</ul>
<p>For this reason we do not display a cookie banner.</p>

<h2 id="s4">Local storage</h2>
<p>localStorage, sessionStorage and IndexedDB are not used.</p>

<h2 id="s5">Changes</h2>
<p>If non-technical cookies are added in the future, this policy will be updated and prior consent will be requested.</p>
H;
}

// Helper: carga el texto legal en el idioma actual y sustituye placeholders.
function legal_render(string $slug, array $vars): string {
    $lang = current_lang();
    $fn = 'legal_' . $slug . '_' . $lang;
    if (!function_exists($fn)) $fn = 'legal_' . $slug . '_es';
    $html = call_user_func($fn);
    foreach ($vars as $k => $v) $html = str_replace('%%' . $k . '%%', (string)$v, $html);
    return $html;
}
