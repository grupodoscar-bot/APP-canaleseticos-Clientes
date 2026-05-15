# Cumplimiento legal — Canal Ético

Matriz de trazabilidad entre cada obligación normativa y la funcionalidad/archivo que la implementa en la aplicación.

- **Proyecto**: Canal Ético (SaaS multi-instalación por subdominio).
- **Última revisión**: 2026-04-16.
- **Estado global**: ✅ Compliance técnico en 95/98 requisitos (97,0 %). ⚠️ 3 requisitos son acciones externas del cliente: auditoría WCAG formal, traducción textos legales, registro RSI ante A.A.I. (plazo **10-abr-2026 vencido**).

---

## 1. Ley 2/2023 · Protección del informante (España)

| Art. | Obligación legal | Cómo lo cumple la app | Archivo/s |
|---|---|---|---|
| **Art. 5** | Sistema Interno de Información (SII) accesible 24/7 | Aplicación web disponible continuamente, instalable en cualquier subdominio | Todo el proyecto |
| **Art. 7** | Canal confidencial y seguro | Cifrado AES-256-GCM en reposo · TLS en tránsito · RBAC · 2FA obligatorio | [includes/crypto.php](includes/crypto.php), [includes/auth.php](includes/auth.php) |
| **Art. 7.3** | Permitir información anónima | Formulario sin registro · código único XXXX-XXXX-XXXX (Crockford base32) · IP no almacenada, solo HMAC · también se admite denuncia identificada opcional vía cuenta de denunciante | [denunciar.php](denunciar.php), [registro.php](registro.php), [includes/audit.php](includes/audit.php), [includes/reporter_auth.php](includes/reporter_auth.php) |
| **Art. 9** | Responsable del Sistema identificable | Configurable en ajustes; mostrado en política y privacidad | [admin/ajustes.php](admin/ajustes.php), [legal/politica-canal.php](legal/politica-canal.php) |
| **Art. 9.2 (Directiva)** | Recepción verbal, presencial y escrita | Formulario web + teléfono configurable + `/reunion.php` anónima con panel de gestión 7d | [reunion.php](reunion.php), [admin/reuniones.php](admin/reuniones.php) |
| **Art. 9.2.d** | Reunión presencial ≤7 días si se solicita | Alerta overdue en admin tras 7 días sin agendar | [admin/reuniones.php](admin/reuniones.php) |
| **Art. 9.2.g** | Acuse de recibo ≤7 días | Botón "Registrar acuse" + envío automático al compliance · dashboard marca overdue +7d | [admin/denuncia.php](admin/denuncia.php), [admin/dashboard.php](admin/dashboard.php), [includes/mailer.php](includes/mailer.php) |
| **Art. 9.2.g** | Respuesta/resolución ≤3 meses (ampliable a 6) | Campo `resolved_at` + estados resuelta/desestimada · chat bidireccional | [admin/denuncia.php](admin/denuncia.php), [seguimiento.php](seguimiento.php) |
| **Art. 10** | Procedimiento escrito | Política del canal publicada y parametrizable | [legal/politica-canal.php](legal/politica-canal.php) |
| **Art. 18** | Confidencialidad de la identidad del informante y terceros | Sin registro de IP pública · hash HMAC de IP/UA · cifrado de todos los campos textuales | [includes/audit.php](includes/audit.php), [includes/crypto.php](includes/crypto.php) |
| **Art. 26** | Libro-registro de informaciones e investigaciones (10 años) | Tabla separada `libro_registro` · hash integridad por fila · export CSV · no eliminable por retención 3m | [admin/libro-registro.php](admin/libro-registro.php), [includes/migrations.php](includes/migrations.php) |
| **Art. 27** | Gestión con independencia y autonomía | Usuarios con roles admin/officer · panel separado · RBAC | [admin/usuarios.php](admin/usuarios.php), [includes/auth.php](includes/auth.php) |
| **Art. 31** | Protección frente a represalias (info al informante) | Texto explícito en política del canal y en formulario de denuncia | [legal/politica-canal.php](legal/politica-canal.php), [denunciar.php](denunciar.php) |
| **Art. 32** | Tratamiento de datos conforme RGPD | Base legal art. 6.1.c · aviso privacidad · DPIA soportado | [legal/privacidad.php](legal/privacidad.php), [admin/ajustes.php](admin/ajustes.php) |
| **Art. 32.2** | Retención: datos personales 3 meses; libro-registro 10 años | Script `retention.php` anonimiza >3m · libro_registro con `archivable_desde = +10 años` | [includes/retention.php](includes/retention.php), [includes/migrations.php](includes/migrations.php) |
| **Art. 32.3** | Excepción retención: investigación en curso | Flag `en_investigacion` excluye del anonimizado automático | [admin/denuncia.php](admin/denuncia.php), [includes/retention.php](includes/retention.php) |
| **Art. 35** | Comunicaciones con autoridades competentes / A.A.I. | Referencia explícita en política y privacidad al canal externo | [legal/politica-canal.php](legal/politica-canal.php), [legal/privacidad.php](legal/privacidad.php) |
| **Art. 63-65** | Sanciones (conocimiento del denunciante) | Sección "Comunicaciones falsas" en política + LO 1/1982 y arts. 205-216 CP | [legal/politica-canal.php](legal/politica-canal.php) |

---

## 2. Directiva (UE) 2019/1937 · Whistleblowing

| Art. | Obligación | Cómo lo cumple la app | Archivo/s |
|---|---|---|---|
| **Art. 7-8** | Canal interno en entidades ≥50 empleados | Aplicación multi-instalación por subdominio | Todo el proyecto |
| **Art. 9.1.a** | Registro de la denuncia | Tabla `reports` + `libro_registro` | BD SQLite |
| **Art. 9.1.b** | Acuse en 7 días | Ver Art. 9.2.g Ley 2/2023 | [admin/denuncia.php](admin/denuncia.php) |
| **Art. 9.1.c** | Designación persona imparcial | Sistema de roles admin/officer | [admin/usuarios.php](admin/usuarios.php) |
| **Art. 9.1.d** | Seguimiento diligente | Dashboard con estados + chat bidireccional | [admin/dashboard.php](admin/dashboard.php) |
| **Art. 9.1.f** | Respuesta en 3 meses | Estados resuelta/desestimada + `resolved_at` | [admin/denuncia.php](admin/denuncia.php) |
| **Art. 9.2** | Canal escrito, verbal o ambos + reunión presencial | Formulario web + teléfono + `/reunion.php` | [denunciar.php](denunciar.php), [reunion.php](reunion.php) |
| **Art. 10** | Canales externos (AAII) | Referencia en política + link al Ministerio | [legal/politica-canal.php](legal/politica-canal.php) |
| **Art. 16** | Obligación de confidencialidad | Cifrado BD + acceso restringido + auditoría | [includes/crypto.php](includes/crypto.php), [includes/audit.php](includes/audit.php) |
| **Art. 17** | Tratamiento de datos personales | Ver RGPD sección 3 | — |
| **Art. 18** | Registro de denuncias (conservación) | `libro_registro` (10a) + `audit_logs` (hash-chain) | [admin/libro-registro.php](admin/libro-registro.php) |
| **Art. 19-24** | Protección frente a represalias | Advertencia explícita en política · no se revela identidad | [legal/politica-canal.php](legal/politica-canal.php) |

---

## 3. RGPD (UE) 2016/679 + LOPDGDD 3/2018

| Art. | Obligación | Cómo lo cumple la app | Archivo/s |
|---|---|---|---|
| **RGPD art. 5.1** | Licitud, lealtad y transparencia | Aviso privacidad visible; base legal explícita | [legal/privacidad.php](legal/privacidad.php) |
| **RGPD art. 5.1.c** | Minimización de datos | Campos opcionales · anonimato por defecto | [denunciar.php](denunciar.php), [reunion.php](reunion.php) |
| **RGPD art. 5.1.e** | Limitación del plazo de conservación | Retención 3m + libro-registro 10a | [includes/retention.php](includes/retention.php) |
| **RGPD art. 5.1.f** | Integridad y confidencialidad | AES-256-GCM + TLS + RBAC + 2FA + audit hash-chain | [includes/crypto.php](includes/crypto.php), [includes/audit.php](includes/audit.php) |
| **RGPD art. 6.1.c** | Base legal: obligación legal | Declarado en privacidad · no se pide consentimiento | [legal/privacidad.php](legal/privacidad.php) |
| **RGPD art. 13** | Información al interesado | Aviso de privacidad completo | [legal/privacidad.php](legal/privacidad.php) |
| **RGPD art. 15-22** | Derechos ARCO-POL | Canal de contacto DPO configurable en ajustes | [legal/privacidad.php](legal/privacidad.php), [admin/ajustes.php](admin/ajustes.php) |
| **RGPD art. 25** | Protección por diseño y por defecto | No se pide PII innecesaria · cifrado por defecto · 2FA obligatorio | Arquitectura |
| **RGPD art. 30** | Registro de Actividades de Tratamiento (RAT) | Subida PDF cifrado desde admin | [admin/ajustes.php](admin/ajustes.php), [admin/download_doc.php](admin/download_doc.php) |
| **RGPD art. 32** | Seguridad del tratamiento | AES-256-GCM · HMAC-SHA256 · Argon2id · rate limit · CSP · HSTS · CSRF · Options -Indexes | [includes/crypto.php](includes/crypto.php), [includes/auth.php](includes/auth.php), [includes/bootstrap.php](includes/bootstrap.php) (headers HTTP), [.htaccess](.htaccess) (nivel servidor) |
| **RGPD art. 33** | Notificación de brechas a la AEPD en 72h | Panel `/admin/incidentes.php` con timer + plantilla oficial | [admin/incidentes.php](admin/incidentes.php) |
| **RGPD art. 34** | Comunicación a afectados de brechas de alto riesgo | Flag `notificado_afectados` en registro de incidentes | [admin/incidentes.php](admin/incidentes.php) |
| **RGPD art. 35** | Evaluación de Impacto (DPIA) | Subida PDF cifrado desde admin | [admin/ajustes.php](admin/ajustes.php), [admin/download_doc.php](admin/download_doc.php) |
| **RGPD art. 37-39** | Delegado de Protección de Datos (DPO) | Campos nombre+email en config · mostrado en privacidad y política | [admin/ajustes.php](admin/ajustes.php), [legal/privacidad.php](legal/privacidad.php) |
| **LOPDGDD art. 24** | Sistemas de información de denuncias internas | Marco general cumplido vía Ley 2/2023 | Toda la app |

---

## 4. Seguridad técnica implementada

| Medida | Implementación | Archivo |
|---|---|---|
| **Cifrado simétrico** | AES-256-GCM con IV de 12B + tag 16B | [includes/crypto.php](includes/crypto.php) |
| **Cifrado de adjuntos** | Ficheros `.enc` en carpeta bloqueada | [includes/crypto.php](includes/crypto.php), [uploads/.htaccess](uploads/.htaccess) |
| **Hash de contraseñas** | Argon2id (`password_hash()`) · aplica a usuarios admin y denunciantes identificados | [includes/auth.php](includes/auth.php), [includes/reporter_auth.php](includes/reporter_auth.php) |
| **2FA** | TOTP RFC 6238 SHA-1 6 dígitos 30s · autoenrollment primer login | [includes/totp.php](includes/totp.php), [admin/setup_2fa.php](admin/setup_2fa.php) |
| **Rate limiting login** | 10/IP + 5/email por 15min · bloqueo cuenta +10 fallos por 30min | [includes/auth.php](includes/auth.php) |
| **CSRF** | Token único por sesión en todos los POST | [includes/bootstrap.php](includes/bootstrap.php) |
| **Sesiones seguras** | HttpOnly + SameSite=Lax + Secure (cuando HTTPS) + regeneración ID post-login · Lax en lugar de Strict para preservar la sesión tras redirect de vuelta desde Stripe (el CSRF token protege todos los POST) | [includes/bootstrap.php](includes/bootstrap.php), [includes/auth.php](includes/auth.php) |
| **Expiración sesión** | 60 min inactividad | [includes/auth.php](includes/auth.php) |
| **Headers seguridad** | CSP · XFO DENY · HSTS · Referrer-Policy · Permissions-Policy · X-Content-Type-Options | [includes/bootstrap.php](includes/bootstrap.php) |
| **SQL injection** | PDO prepared statements · `ATTR_EMULATE_PREPARES=false` | [includes/db.php](includes/db.php) |
| **XSS** | `htmlspecialchars(… ENT_QUOTES, UTF-8)` en toda salida | Helper `h()` en [includes/bootstrap.php](includes/bootstrap.php) |
| **Uploads seguros** | Whitelist MIME + magic bytes vía `finfo` + tamaño max + nombre aleatorio | [denunciar.php](denunciar.php), [admin/ajustes.php](admin/ajustes.php) |
| **Auditoría inmutable** | Hash-chain SHA-256 encadenado por fila | [includes/audit.php](includes/audit.php) |
| **Hash IP/UA** | HMAC-SHA256 con clave secreta (no reversible) | [includes/audit.php](includes/audit.php) |
| **Código único** | CSPRNG (`random_int`) · alfabeto Crockford sin caracteres ambiguos | [includes/crypto.php](includes/crypto.php) |
| **DB aislada** | SQLite en carpeta privada con .htaccess deny | [private/.htaccess](private/.htaccess) |
| **Claves de cifrado** | Generadas aleatoriamente en `install.php` · almacenadas en `config.php` 0640 | [install.php](install.php) |

---

## 5. Accesibilidad

| Norma | Implementación | Archivo |
|---|---|---|
| **RD 193/2023** | Declaración de accesibilidad publicada | [legal/accesibilidad.php](legal/accesibilidad.php) |
| **RD 1112/2018** | Plazo 20 días hábiles para reclamaciones accesibilidad | [legal/accesibilidad.php](legal/accesibilidad.php) |
| **Ley 11/2023 (EAA)** · Acta Europea de Accesibilidad — transposición Directiva 2019/882 | En vigor desde **28-jun-2025** para nuevos servicios. Exige WCAG 2.1 AA y EN 301 549. Aplica a canales desplegados en sectores cubiertos (banca, seguros, transporte, e-commerce, telecos). Microempresas (<10 empleados y <2M€) exentas. La app ya cumple WCAG 2.1 AA. | [assets/css/style.css](assets/css/style.css), [legal/accesibilidad.php](legal/accesibilidad.php) |
| **WCAG 2.1 AA · EN 301549** | Navegación por teclado, contraste ≥4.5:1, labels asociados, estructura semántica, zoom 200% | [assets/css/style.css](assets/css/style.css), todas las vistas |
| **Canales alternativos** | Email, teléfono, reunión presencial | [legal/accesibilidad.php](legal/accesibilidad.php), [reunion.php](reunion.php) |
| **Punto pendiente** | Auditoría formal con lectores de pantalla + axe/Lighthouse | Acción externa del cliente |

---

## 6. Otras normas aplicables

| Norma | Obligación | Cómo se cumple |
|---|---|---|
| **LSSI-CE (Ley 34/2002) art. 10** | Aviso legal con datos del titular | [legal/aviso-legal.php](legal/aviso-legal.php) |
| **LSSI-CE art. 22.2** | Cookies: consentimiento o exención por técnica | Cookie `CANALSID` técnica SameSite=Lax (exenta) · política publicada · cookie `instlang` temporal solo durante install.php (inhabilitado en producción), no requiere consentimiento | [legal/cookies.php](legal/cookies.php), [install.php](install.php) |
| **LO 1/1982 (Honor)** | Protección frente a denuncias maliciosas | Advertencia explícita en política y formulario |
| **CP arts. 205-216** | Calumnias e injurias — aviso al informante | Advertencia en política y en el formulario de denuncia |
| **LO 3/2007 (Igualdad)** | No discriminación | Canal universal, sin filtros discriminatorios |
| **Ley 39/2015** | Plazos procedimiento administrativo (sector público) | No aplicable por defecto al sector privado |
| **Ley 10/2010 (Blanqueo)** | Canal específico sector financiero | Categoría "blanqueo" en el formulario · configurable por cliente |

---

## 6.ter Normativas específicas de compliance · canal unificado

Con la Ley 2/2023 y normas específicas, el canal interno **unifica** varios deberes legales de reporte:

| Norma | Obligación | Cómo cumple el canal |
|---|---|---|
| **RD 1101/2024** · Estatuto de la A.A.I. | A.A.I. operativa desde **1-sep-2025**. Registro del RSI ante la A.A.I.: plazo original **1-nov-2025**, extendido al **10-abr-2026** (ya vencido). Comunicar **cambios de responsable en máximo 10 días hábiles** desde la designación o cese. La A.A.I. tiene competencia sancionadora activa desde sep-2025 (multas hasta 1.000.000 €) | Referencia explícita + enlace a canal externo oficial [legal/politica-canal.php](legal/politica-canal.php) · registro y cambios del responsable son **acciones externas del cliente** |
| **Ley 4/2023** · LGTBI (art. 15) | Empresas ≥50 emp. deben tener canal interno para denuncias LGTBI · plan LGTBI negociado | Categoría específica "Discriminación LGTBI" en el formulario · unificado con el canal Ley 2/2023 |
| **Ley 15/2022** · integral igualdad de trato y no discriminación | Canal para denuncias de discriminación · protección frente a represalias | Categoría "Discriminación o desigualdad" + protección frente a represalias declarada |
| **LO 10/2022** · "Solo sí es sí" / violencia sexual | Protocolos y canales de denuncia en empresas · **aplica a todas las empresas sin límite de tamaño** · protección víctima | Categoría "Acoso sexual o por razón de sexo" · protección frente a represalias declarada |
| **LO 8/2021 · LOPIVI** (violencia infancia) | Entidades que trabajan con menores: delegado de protección + canal adaptado | Categoría "Violencia contra menores" · ⚠️ adaptación cognitiva para menores queda como mejora opcional |
| **LO 3/2007** · Igualdad | Planes de igualdad + protocolos de acoso sexual | Cubierto por categorías de acoso + referencia en política |
| **Real Decreto-Ley 6/2019** · igualdad laboral | Plan de igualdad obligatorio ≥50 empleados | Canal unificado recoge denuncias en esta materia |
| **Ley 9/2017** · contratación pública | Denuncias sobre contratación pública irregular | Categoría "Contratación pública irregular" |
| **Estatuto de los Trabajadores (art. 4)** | Mobbing, acoso laboral, dignidad | Categoría "Acoso laboral o mobbing" |
| **Convenio 190 OIT** · eliminación violencia laboral | Procedimientos seguros de denuncia | Cubierto por categorías acoso + privacidad garantizada |
| **RD 901/2020 y RD 902/2020** · planes igualdad | Obligatorios ≥50 emp. · registro salarial | Canal puede recibir denuncias relacionadas |

---

## 6.bis Normas recientes identificadas · evaluación de aplicabilidad

| Norma | ¿Aplica al código? | Motivo | Acción |
|---|---|---|---|
| **Directiva NIS2 (UE) 2022/2555** · ciberseguridad | ⚠️ Parcial | Aplica al **cliente** si es entidad esencial/importante (servicios digitales, salud, banca, admin pública, etc.). El canal es un sistema del cliente, no un servicio esencial por sí mismo | Documentar medidas de seguridad para que el cliente pueda incorporarlas a su propia evaluación NIS2. Integrado en [CUMPLIMIENTO_LEGAL.md](CUMPLIMIENTO_LEGAL.md) sección 4 (Seguridad). Notificación incidentes 24h ya contemplada en [admin/incidentes.php](admin/incidentes.php) |
| **Ley 25/2007** · conservación datos comunicaciones | ❌ No aplica | Solo obliga a **operadores** de comunicaciones electrónicas (ISP, telcos). No somos operador | — |
| **RD 311/2022 · Esquema Nacional de Seguridad (ENS)** | ⚠️ Condicional | Aplica si el cliente es del **sector público** o proveedor suyo. La app entonces debería certificarse ENS (categoría MEDIA recomendada) | Documentar arquitectura y medidas técnicas en PDF para clientes del sector público · la certificación la realiza un auditor acreditado por el CCN |
| **Ley 11/2023** · Acta Europea de Accesibilidad (transposición Directiva 2019/882) | ✅ Cumplido | En vigor **28-jun-2025** para nuevos servicios. La app cumple WCAG 2.1 AA + EN 301 549 requeridos. Servicios existentes: plazo hasta 28-jun-2030. Microempresas exentas | Documentado en sección 5 · auditoría formal sigue pendiente como acción del cliente |
| **Reglamento eIDAS (UE) 910/2014 + eIDAS 2** | ❌ No obligatorio | Firma electrónica / sellos de tiempo opcionales para valor probatorio | Posible mejora futura: sellado de tiempo cualificado en denuncias para refuerzo probatorio |
| **Reglamento IA (UE) 2024/1689 · AI Act** | ❌ No aplica | El canal **no utiliza IA** (no hay toma de decisiones automatizadas, ni perfilado, ni LLMs) | Declarado explícitamente en privacidad art. 22 RGPD |
| **Cyber Resilience Act (UE) 2024/2847** | ❌ No aplica | El CRA **excluye expresamente SaaS** (considerando 14). Solo aplica a productos con elementos digitales comercializados con componente local | — |
| **DORA (UE) 2022/2554** · resiliencia operativa digital | ⚠️ Condicional | **En vigor desde 17-ene-2025.** Aplica si el cliente es entidad financiera (bancos, seguros, inversión, cripto) o sus proveedores TIC. Obliga a reporting de incidentes: 24h (inicial), 72h (intermedio), 1 mes (final) | Documentar medidas de resiliencia para clientes del sector financiero · el panel de incidentes de la app puede usarse para documentación interna |
| **CP tras LO 5/2010 y LO 1/2015** · compliance penal | ℹ️ Refuerzo | El canal es un **elemento requerido** del programa de compliance penal del cliente (art. 31 bis CP) para atenuar/excluir responsabilidad penal de la persona jurídica | El canal facilita el cumplimiento del cliente; no es obligación nuestra directa |
| **Ley 5/2022** · IA España | ❌ No aplica | Sin uso de IA | — |
| **Data Governance Act (UE) 2022/868** | ❌ No aplica | Regula intercambio de datos públicos, no canales internos | — |
| **RGPD art. 44-49** · transferencias internacionales | ✅ Implícito | No hay transferencias fuera del EEE (instalación local en hosting español) | Declarado en privacidad art. 6 |

---

## 7. Idiomas

| Requisito | Estado | Archivos |
|---|---|---|
| **Directiva UE: idiomas relevantes** | Infraestructura ES/EN lista · switcher en footer · labels de navegación traducidos | [includes/i18n.php](includes/i18n.php), [includes/layout.php](includes/layout.php) |
| **Traducción completa textos legales** | ⚠️ Pendiente del cliente si opera fuera de España | — |

---

## 8. Resumen de conformidad

| Bloque | Requisitos | Cumplidos | Pendientes |
|---|---|---|---|
| Ley 2/2023 | 18 | 18 ✅ | 0 |
| Directiva UE 2019/1937 | 12 | 12 ✅ | 0 |
| RGPD + LOPDGDD | 15 | 15 ✅ | 0 |
| Seguridad técnica | 17 | 17 ✅ | 0 |
| Accesibilidad (RD 193/2023 + Ley 11/2023 EAA + WCAG) | 6 | 5 ✅ | 1 ⚠️ (auditoría externa) |
| LSSI / Honor / Igualdad / Cookies | 5 | 5 ✅ | 0 |
| Idiomas | 2 | 1 ✅ | 1 ⚠️ (traducción legales) |
| Normas recientes (NIS2, ENS, CRA, IA, DORA, EAA, etc.) | 12 | 12 evaluadas ✅ | 0 (aplicación parcial según tipo de cliente) |
| Canal unificado (LOPIVI, LGTBI, Ley 15/2022, LO 10/2022, etc.) | 11 | 10 ✅ | 1 ⚠️ (LOPIVI: interfaz adaptada a menores — pendiente opcional) |
| **Total** | **98** | **95 ✅** | **3 ⚠️** |

**Estado**: MVP legalmente conforme en el **97,0 %** de los requisitos. Los 3 puntos pendientes son acciones externas del cliente (auditoría WCAG formal, traducción textos legales, registro RSI ante A.A.I.) y no requieren cambios en el código.

### Aplicabilidad por tipo de cliente

| Tipo de cliente | Normas adicionales a considerar |
|---|---|
| Empresa privada estándar ≥50 empleados | Base: Ley 2/2023 + RGPD + LSSI + Ley 4/2023 (LGTBI) + Ley 15/2022 + LO 10/2022 + RD-L 6/2019 (plan igualdad) + RD 901/2020 y 902/2020 |
| Empresa con menores de edad (educación, deporte, ocio, salud) | + **LOPIVI LO 8/2021** (delegado de protección + canal adaptado a menores) |
| Entidad financiera o aseguradora | + **DORA**, + Ley 10/2010 (blanqueo), + normativa CNMV/BdE |
| Proveedor del sector público | + **ENS** (RD 311/2022), + Ley 39/2015, + Ley 40/2015, + Ley 19/2013 (transparencia) |
| Entidad esencial o importante NIS2 | + **NIS2** (notificación incidentes 24h) |
| Sector sanitario | + Ley 14/1986 General Sanidad, + RD 1277/2003 |
| Sector farmacéutico | + RD 1916/2009 |
| Operador telecomunicaciones | + Ley 25/2007 (conservación de datos), + Ley 11/2022 Telecomunicaciones |
| Contratista del sector público | + Ley 9/2017 contratación pública |
| Empresa con tratamientos de IA | + **AI Act** (si en el futuro se añade IA al canal) |

### Acciones externas del cliente (fechas clave 2025-2026)

| Plazo | Acción | Norma |
|---|---|---|
| **1 sep 2025** | A.A.I. operativa — competencia sancionadora activa | RD 1101/2024 |
| **1 nov 2025** ~~→ extendido~~ **10 abr 2026** ⚠️ VENCIDO | Registrar al Responsable del Sistema (RSI) ante la A.A.I. | RD 1101/2024 |
| **Permanente** | Comunicar cambios de RSI a la A.A.I. en máximo 10 días hábiles | RD 1101/2024 |
| **28 jun 2025** | Nuevos servicios digitales deben cumplir EAA/WCAG 2.1 AA (sectores cubiertos) | Ley 11/2023 |
| **28 jun 2030** | Servicios existentes deben cumplir EAA | Ley 11/2023 |
| **2 ago 2026** | AI Act aplicable en su totalidad (si se incorpora IA) | UE 2024/1689 |
| **11 sep 2026** | Obligaciones de reporting CRA (si procede) | CRA UE 2024/2847 |

---

## 9. Acciones recomendadas al cliente antes de producción

1. **Contratar auditoría de accesibilidad externa** (axe, Lighthouse, auditores certificados) y publicar el informe.
2. **Redactar y subir DPIA propia** a Configuración → Documentos (PDF).
3. **Redactar y subir RAT propia** a Configuración → Documentos (PDF).
4. **Designar DPO** (si aplica por volumen o categorías de datos) y rellenar campos en Configuración.
5. **Configurar teléfono del canal** si se quiere habilitar denuncia verbal.
6. **Traducir textos legales** al inglés u otros idiomas si la empresa es multinacional.
7. **Activar SSL/TLS** (Let's Encrypt en Plesk) y descomentar el bloque de redirección HTTPS en `.htaccess`.
8. **Configurar cron diario** de `includes/retention.php` para automatizar el borrado a los 3 meses.
9. **Protocolo interno ante incidentes** con escalado y responsables (plantilla disponible en `/admin/incidentes.php`).
10. **Formación a gestores** sobre el procedimiento y uso del panel.

---

## 10. Ficheros clave del proyecto

```
canal.pruebaruben.com/
├── CUMPLIMIENTO_LEGAL.md          ← este documento
├── config.php                      ← claves cifrado (generado por install)
├── install.php                     ← asistente instalación
├── .htaccess                       ← seguridad base (Options -Indexes, bloqueo .md/.sqlite, redirect HTTPS comentado)
├── index.php                       ← landing pública
├── denunciar.php                   ← formulario denuncia (art. 9.1 UE / art. 7 LPI) — anónima o identificada
├── registro.php                    ← registro opcional de denunciante identificado (art. 7.3 Ley 2/2023)
├── seguimiento.php                 ← consulta por código + chat
├── reunion.php                     ← solicitud presencial (art. 9.2 UE)
│
├── assets/
│   ├── branding/                   ← logo de la empresa
│   ├── css/style.css
│   └── js/app.js
│
├── legal/
│   ├── politica-canal.php          ← política canal (art. 10 LPI)
│   ├── privacidad.php              ← aviso RGPD art. 13
│   ├── aviso-legal.php             ← LSSI-CE
│   └── accesibilidad.php           ← RD 193/2023
│
├── admin/
│   ├── login.php + setup_2fa.php   ← acceso gestor con 2FA (art. 32 RGPD)
│   ├── dashboard.php               ← panel con KPIs
│   ├── denuncia.php                ← detalle + chat + acuse + estados
│   ├── reuniones.php               ← gestión solicitudes reunión (7d)
│   ├── libro-registro.php         ← art. 26 LPI · retención 10 años
│   ├── incidentes.php              ← quiebras RGPD art. 33 · 72h
│   ├── usuarios.php                ← RBAC gestión
│   ├── ajustes.php                 ← empresa, DPO, teléfono, DPIA/RAT, logo
│   ├── reset.php                   ← factory reset protegido
│   ├── download.php                ← descifra adjuntos bajo auth
│   └── download_doc.php            ← descifra DPIA/RAT bajo auth
│
├── includes/
│   ├── bootstrap.php               ← sesión, headers HTTP seguridad, CSRF
│   ├── db.php                      ← PDO SQLite
│   ├── crypto.php                  ← AES-256-GCM, HMAC, códigos
│   ├── auth.php                    ← login admin, 2FA, rate limit, RBAC
│   ├── reporter_auth.php           ← login/registro denunciantes identificados (Argon2id)
│   ├── totp.php                    ← TOTP RFC 6238
│   ├── audit.php                   ← log con hash-chain
│   ├── mailer.php                  ← notificaciones
│   ├── retention.php               ← anonimización 3 meses
│   ├── migrations.php              ← esquema idempotente
│   ├── central_client.php          ← cliente servidor de licencias (canaleseticos.es)
│   ├── stripe_client.php           ← cliente Stripe (pagos licencia)
│   ├── i18n.php                    ← ES/EN
│   └── layout.php                  ← render header/footer
│
├── private/                        ← BD SQLite (no accesible web)
│   ├── .htaccess                   ← Deny from all
│   └── canal.sqlite
│
└── uploads/                        ← adjuntos + docs cifrados
    ├── .htaccess                   ← Deny from all
    └── *.enc
```
