<x-layouts.admin title="Update">

    @if(session('update_success'))
        @php $us = session('update_success'); @endphp
        <div style="background:rgba(63,185,80,0.12); border:1px solid var(--status-online); border-radius:var(--border-radius-md); padding:16px; margin-bottom:20px;">
            <div style="font-weight:600; color:var(--status-online); margin-bottom:8px;">
                ✔ Mise à jour vers la version {{ $us['version'] }} appliquée avec succès !
            </div>
            <ul style="margin:0; padding-left:18px; font-size:var(--font-size-sm); color:var(--text-secondary);">
                @foreach($us['log'] as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Current status --}}
    <div style="border:1px solid var(--border); border-radius:var(--border-radius-md); padding:20px; margin-bottom:20px; background:var(--bg-surface);">
        <h2 style="margin:0 0 16px; font-size:var(--font-size-lg); color:var(--text-primary);">État de la version</h2>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:16px;">
            <div>
                <div style="font-size:var(--font-size-xs); color:var(--text-muted); text-transform:uppercase; letter-spacing:.06em; margin-bottom:4px;">Version installée</div>
                <div style="font-size:1.4rem; font-weight:700; color:var(--text-primary); font-family:var(--font-family-mono);">
                    {{ $versionInfo['installed'] }}
                </div>
            </div>

            <div>
                <div style="font-size:var(--font-size-xs); color:var(--text-muted); text-transform:uppercase; letter-spacing:.06em; margin-bottom:4px;">Dernière version (GitHub)</div>
                @if($versionInfo['latest'])
                    <div style="font-size:1.4rem; font-weight:700; font-family:var(--font-family-mono);
                        color:{{ $versionInfo['upToDate'] ? 'var(--status-online)' : 'var(--accent-primary)' }}">
                        {{ $versionInfo['latestTag'] }}
                    </div>
                @else
                    <div style="font-size:var(--font-size-sm); color:var(--text-muted);">Impossible de contacter GitHub</div>
                @endif
            </div>

            <div style="display:flex; align-items:center;">
                @if($versionInfo['latest'] === null)
                    <span style="padding:6px 14px; background:rgba(139,148,158,0.15); color:var(--text-muted); border-radius:20px; font-size:var(--font-size-sm);">
                        ⚠ Hors ligne
                    </span>
                @elseif($versionInfo['upToDate'])
                    <span style="padding:6px 14px; background:rgba(63,185,80,0.15); color:var(--status-online); border-radius:20px; font-size:var(--font-size-sm);">
                        ✔ À jour
                    </span>
                @else
                    <span style="padding:6px 14px; background:rgba(210,153,34,0.2); color:var(--accent-primary); border-radius:20px; font-size:var(--font-size-sm); font-weight:600;">
                        ↑ Mise à jour disponible
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- Release notes --}}
    @if($versionInfo['latest'] && ! $versionInfo['upToDate'])
        @php $release = $versionInfo['latest']; @endphp
        <div style="border:1px solid var(--border); border-radius:var(--border-radius-md); padding:20px; margin-bottom:20px; background:var(--bg-surface);">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; gap:12px; flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0 0 4px; font-size:var(--font-size-lg); color:var(--text-primary);">
                        {{ $release['name'] ?? ('v' . $versionInfo['latestTag']) }}
                    </h2>
                    @if(isset($release['published_at']))
                        <div style="font-size:var(--font-size-xs); color:var(--text-muted);">
                            Publié le {{ \Carbon\Carbon::parse($release['published_at'])->format('d/m/Y') }}
                        </div>
                    @endif
                </div>
                <a href="{{ $release['html_url'] ?? '#' }}" target="_blank" rel="noopener"
                   class="hlx-btn-secondary" style="font-size:var(--font-size-sm);">
                    Voir sur GitHub ↗
                </a>
            </div>

            @if(! empty($release['body']))
                <div style="background:var(--bg-surface-alt); border-radius:var(--border-radius-sm); padding:14px;
                            font-size:var(--font-size-sm); color:var(--text-secondary); white-space:pre-wrap;
                            font-family:var(--font-family-mono); max-height:300px; overflow-y:auto; line-height:1.6;">{{ $release['body'] }}</div>
            @endif
        </div>

        {{-- Apply update --}}
        <div style="border:1px solid var(--accent-primary); border-radius:var(--border-radius-md); padding:20px; background:rgba(210,153,34,0.05);">
            <h2 style="margin:0 0 10px; font-size:var(--font-size-lg); color:var(--text-primary);">Appliquer la mise à jour</h2>

            <ul style="font-size:var(--font-size-sm); color:var(--text-secondary); margin:0 0 16px; padding-left:18px; line-height:1.8;">
                <li>Le fichier <code>.env</code>, le dossier <code>storage/</code> et <code>vendor/</code> ne seront <strong>jamais modifiés</strong></li>
                <li>Les migrations seront exécutées automatiquement</li>
                <li><code>php artisan optimize:clear &amp;&amp; optimize</code> sera lancé</li>
                <li><code>composer install</code> sera tenté si disponible, sinon à lancer manuellement</li>
                <li>Une sauvegarde préalable de la base de données est fortement recommandée</li>
            </ul>

            <form method="POST" action="{{ route('admin.update.apply') }}"
                  onsubmit="return confirm('Appliquer la mise à jour {{ $versionInfo['latestTag'] }} ?\n\nCette opération remplace les fichiers de l\'application.\nAssurez-vous d\'avoir sauvegardé votre base de données.')">
                @csrf
                <button type="submit" class="hlx-btn-gold" style="font-size:var(--font-size-base);">
                    ↑ Mettre à jour vers {{ $versionInfo['latestTag'] }}
                </button>
            </form>
        </div>

    @elseif($versionInfo['latest'] && $versionInfo['upToDate'])
        <div style="border:1px solid var(--border); border-radius:var(--border-radius-md); padding:16px; background:var(--bg-surface);
                    font-size:var(--font-size-sm); color:var(--text-muted); text-align:center;">
            Vous utilisez la dernière version disponible. Aucune action requise.
        </div>
    @endif

</x-layouts.admin>
