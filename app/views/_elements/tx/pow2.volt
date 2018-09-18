<span class="ui yellow label">
  {% if item[1]['block'] >= 7353250 %}
    +<?php echo number_format($props['virtual_supply'] * 0.095 * 0.1 / 360 / (86400/3) * (21/25), 8, '.', ''); ?> BP
  {% else %}
    +1 BP
  {% endif %}
</span>
<strong>{{ item[1]['op'][1]['work'][1]['input']['worker_account'] }} found a block</strong>
