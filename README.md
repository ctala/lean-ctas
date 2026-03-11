# Eco CTA Plugin

Plugin WordPress lean para inyectar CTAs dinámicos dentro del contenido según post type, taxonomía o categoría.

**Sin dependencias. Sin bloat. Un archivo PHP. Funciona con cualquier post type y taxonomía.**

---

## ¿Qué hace?

Inserta automáticamente un bloque CTA en posts, páginas, CPTs — donde tú quieras — seleccionando el CTA adecuado según el post type y/o taxonomía.

```
[Párrafo 1]
[Párrafo 2]
[Párrafo 3]
─────────────────────────────────────
  ⚡ ¿Buscas financiamiento?
  Cada semana enviamos convocatorias a tu bandeja.
  [ 📧 Suscribirme gratis ]
─────────────────────────────────────
[Párrafo 4...]
```

## Casos de uso

- **Blog de noticias** → diferente CTA para Tecnología, Startups, Fintech
- **Glosario (CPT)** → CTA de comunidad/newsletter en cada entrada
- **WooCommerce** → CTA en productos por categoría
- **Cualquier CPT con taxonomías** → el plugin detecta todo automáticamente

## Instalación

1. Subir carpeta `eco-cta-plugin/` a `/wp-content/plugins/`
2. Activar desde **Plugins → Plugins instalados**
3. Configurar en **Ajustes → Eco CTA**

## Configuración

### Panel Admin (Ajustes → Eco CTA)

**Ajustes globales:**
- **Activado:** Encender/apagar
- **Post Types habilitados:** Checkboxes para posts, páginas, CPTs
- **Insertar inline después del párrafo N°:** Posición del CTA inline

**Por cada CTA:**

| Campo | Descripción |
|-------|-------------|
| Post Type | Filtrar por post type específico. Vacío = todos los habilitados |
| Taxonomía/Término | Filtrar por cualquier taxonomía (categorías, tags, custom). Vacío = sin filtro |
| Posición | Inline (párrafo N) / Final del post / Ambos |
| Color de acento | Color del borde y botón |
| Título | Texto en negrita arriba |
| Texto | Descripción breve |
| Tipo de botón | Link / Newsletter / Comunidad |
| URL | Destino al hacer clic |

### Lógica de matching (prioridad)

1. **Post type + término** → match más específico
2. **Solo término** → match por taxonomía sin importar post type
3. **Solo post type** → match genérico para ese tipo de contenido
4. **Fallback global** → CTA sin filtros (aplica a todo)

### Shortcode

```
[eco_cta]                                          <!-- fallback global -->
[eco_cta category="16"]                            <!-- legacy: categoría WP -->
[eco_cta post_type="glosario"]                     <!-- por post type -->
[eco_cta taxonomy="category" term="16"]            <!-- por taxonomía -->
[eco_cta post_type="product" taxonomy="product_cat" term="42"]  <!-- combo -->
```

## Estructura

```
eco-cta-plugin/
└── eco-cta-plugin.php    ← todo el plugin (un solo archivo)
```

## Personalización CSS

```css
.eco-cta-block {
    --eco-accent: #FF6B35;  /* cambiar desde admin o CSS del tema */
}
```

## Roadmap

- [x] Soporte multi post type y taxonomías
- [x] Posición configurable (inline / final / ambos)
- [ ] Tracking de clicks (GA4 event / endpoint propio)
- [ ] Variantes A/B por categoría
- [ ] Soporte para múltiples CTAs por post (rotación)
- [ ] Widget sidebar opcional

## Licencia

GPL-2.0+
