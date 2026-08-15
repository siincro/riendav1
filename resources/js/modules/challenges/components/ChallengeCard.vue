<script setup lang="ts">
import { Swords, Trophy } from '@lucide/vue';
import { computed } from 'vue';
import type { Challenge } from '@/modules/challenges/types';
import ParticipantAvatar from '@/modules/participants/components/ParticipantAvatar.vue';

/**
 * La ficha de un reto: los dos vaqueros frente a frente con su tiempo
 * acumulado, y el que va ganando marcado.
 *
 * Va siempre sobre fondo blanco —también en el cartel, donde resalta contra el
 * cuero—, así que la tinta es oscura en los dos tonos. Lo que cambia con el
 * tono es la voz: tipografía de cartel y acentos de brasa en la cara pública,
 * la neutra del panel en administración.
 */
const props = withDefaults(
    defineProps<{
        challenge: Challenge;
        tone?: 'panel' | 'cartel';
    }>(),
    { tone: 'panel' },
);

const isCartel = computed(() => props.tone === 'cartel');

const styles = computed(() =>
    isCartel.value
        ? {
              card: 'rounded-[20px] border border-rienda-noche/10 bg-white p-6 shadow-[0_18px_40px_rgba(0,0,0,0.25)]',
              code: 'font-condensed text-sm tracking-[0.18em] text-rienda-fuego uppercase',
              badge: 'border font-condensed text-sm tracking-[0.14em] uppercase',
              badgeOpen:
                  'border-rienda-brasa/40 bg-rienda-brasa/10 text-rienda-brasa',
              badgeClosed:
                  'border-rienda-noche/15 bg-rienda-noche/5 text-rienda-noche/60',
              name: 'font-condensed text-[19px] font-semibold tracking-[0.04em] text-rienda-noche uppercase',
              muted: 'text-rienda-noche/55',
              time: 'font-display text-[21px] text-rienda-noche/70',
              timeLeader: 'font-display text-[21px] text-rienda-fuego',
              rounds: 'font-condensed tracking-[0.12em] text-rienda-noche/55 uppercase',
              leader: 'text-rienda-fuego',
              fallback:
                  'bg-rienda-fuego/15 font-condensed tracking-[0.06em] text-rienda-fuego',
          }
        : {
              card: 'rounded-xl border border-zinc-200 bg-white p-5',
              code: 'text-xs font-medium text-zinc-500',
              badge: 'text-xs font-medium',
              badgeOpen: 'bg-emerald-500/15 text-emerald-700',
              badgeClosed: 'bg-zinc-100 text-zinc-500',
              name: 'font-medium text-zinc-900',
              muted: 'text-zinc-500',
              time: 'font-mono text-sm font-semibold text-zinc-900',
              timeLeader: 'font-mono text-sm font-semibold text-emerald-600',
              rounds: 'text-zinc-500',
              leader: 'text-emerald-600',
              fallback: 'bg-zinc-100 text-zinc-600',
          },
);
</script>

<template>
    <div class="flex flex-col gap-4" :class="styles.card">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="flex items-center gap-2" :class="styles.code">
                <Swords class="size-4" />
                {{ challenge.public_id }}
            </span>

            <span
                class="rounded-full px-3 py-1"
                :class="[
                    styles.badge,
                    challenge.status === 'open'
                        ? styles.badgeOpen
                        : styles.badgeClosed,
                ]"
            >
                {{ challenge.status_label }}
            </span>
        </div>

        <div
            v-for="contender in challenge.contenders"
            :key="contender.participant.public_id"
            class="flex items-center gap-3"
        >
            <ParticipantAvatar
                :name="contender.participant.full_name"
                :photo-url="contender.participant.photo_url"
                :fallback-class="styles.fallback"
                class="size-10 shrink-0"
            />

            <div class="min-w-0 flex-1">
                <p
                    class="flex items-center gap-1.5 truncate"
                    :class="styles.name"
                >
                    {{ contender.participant.full_name }}
                    <Trophy
                        v-if="contender.is_leader"
                        class="size-4 shrink-0"
                        :class="styles.leader"
                    />
                </p>
                <p class="truncate text-xs" :class="styles.muted">
                    <template v-if="contender.category">
                        {{ contender.category.name }}
                    </template>
                    <template v-else>Sin categoría en este evento</template>
                </p>
            </div>

            <div class="shrink-0 text-right">
                <p
                    class="tabular-nums"
                    :class="
                        contender.is_leader ? styles.timeLeader : styles.time
                    "
                >
                    {{ contender.total_time_formatted ?? 'Sin tiempo' }}
                </p>
                <!--
                    Los aciertos deciden el reto y el tiempo desempata, así que
                    los dos números van juntos: sin los aciertos, el tiempo del
                    ganador puede parecer el peor de los dos.
                -->
                <p class="text-xs" :class="styles.rounds">
                    {{ contender.total_successful_rounds }} aciertos
                </p>
            </div>
        </div>

        <p v-if="challenge.is_tied" class="text-xs" :class="styles.muted">
            Van iguales: mismos aciertos y mismo tiempo.
        </p>

        <slot />
    </div>
</template>
