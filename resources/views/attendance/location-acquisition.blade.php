<div class="mt-3" data-geo-acquisition>
    <p class="form-hint">Se buscarán lecturas durante un máximo de 15 segundos por intento. Un valor menor de precisión indica menor incertidumbre. Medir no abre el registro ni marca asistencia.</p>
    <div class="live-status mb-2" role="status" aria-live="polite" aria-atomic="true" data-geo-diagnostic>Mejor precisión reciente obtenida: sin medir. Requisito por dispositivo: ≤ {{ number_format(config('geolocation.max_accuracy_m'), 2, ',', '') }} m. La distancia más ambas incertidumbres debe ser ≤ {{ config('geolocation.radius_m') }} m respecto a la referencia del docente.</div>
    <div class="d-flex flex-wrap gap-2 mb-3">
        <button type="button" class="btn btn-outline-dark" data-measure-location>Medir precisión sin registrar</button>
        <button type="button" class="btn btn-outline-secondary" data-cancel-location hidden>Cancelar adquisición</button>
    </div>
</div>
