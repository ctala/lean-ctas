# Eco CTA Plugin

Plugin WordPress lean para inyectar CTAs dinámicos dentro del contenido según la categoría del post.

**Sin dependencias. Sin bloat. Un archivo PHP.**

---

## ¿Qué hace?

Inserta automáticamente un bloque CTA después del párrafo N de cada post, seleccionando el CTA adecuado según la categoría del post.

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

- Blog de noticias → diferente CTA para Tecnología, Startups, Fintech
- Media → newsletter específico por nicho
- Cualquier site con contenido categorizado que quiera aumentar conversión

## Instalación

1. Subir carpeta `eco-cta-plugin/` a `/wp-content/plugins/`
2. Activar desde **Plugins → Plugins instalados**
3. Configurar en **Ajustes → Eco CTA**

## Configuración

### Panel Admin

Ir a **Ajustes → Eco CTA**:

- **Activado:** Encender/apagar el plugin
- **Insertar después del párrafo N°:** Posición del CTA (default: 3)
- **CTAs por categoría:** Agregar tantos como necesites

### Por cada CTA:

| Campo | Descripción |
|-------|-------------|
| Categoría | Qué categoría dispara este CTA. "Todas" = fallback |
| Color de acento | Color del borde y botón |
| Título | Texto en negrita arriba |
| Texto | Descripción breve |
| Tipo de botón | Link / Newsletter / Comunidad |
| URL | Destino al hacer clic |

### Lógica de matching

1. Si el post tiene una categoría con CTA configurado → usa ese
2. Si no hay match específico → usa el CTA con categoría "Todas"
3. Si no hay ninguno → no inyecta nada

### Shortcode

También puedes insertar un CTA manualmente:

```
[eco_cta category="16"]
```

Donde `16` es el ID de categoría. Sin parámetros usa el CTA fallback.

## Estructura

```
eco-cta-plugin/
└── eco-cta-plugin.php    ← todo el plugin (un solo archivo)
```

## Personalización CSS

El bloque CTA tiene clase `.eco-cta-block` y usa CSS custom properties:

```css
.eco-cta-block {
    --eco-accent: #FF6B35;  /* color de acento */
}
```

Puedes sobrescribir desde tu tema sin modificar el plugin.

## Roadmap

- [ ] Tracking de clicks (GA4 event / endpoint propio)
- [ ] Variantes A/B por categoría
- [ ] Soporte para múltiples CTAs por post (rotación)
- [ ] Widget sidebar opcional

## Licencia

GPL-2.0+
