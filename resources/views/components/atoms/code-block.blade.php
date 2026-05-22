@props([
    'language' => 'json',
    'copyable' => true,
    'searchable' => true,
    'maxHeight' => 'max-h-96',
])

@once
    <script>
        if (!window.codeBlock) {
            window.codeBlock = (language) => ({
                copied: false,
                searchOpen: false,
                searchQuery: '',
                matchCount: 0,
                currentMatch: 0,
                originalText: '',
                colorizedHtml: '',

                init() {
                    // setTimeout(0) memastikan berjalan SETELAH Livewire morphdom selesai
                    setTimeout(() => this._doHighlight(), 0);

                    // MutationObserver: re-apply setiap kali morphdom mengembalikan ke plain text
                    this._observer = new MutationObserver(() => {
                        const el = this.$refs.codeContent;
                        if (!el) return;
                        const html = el.innerHTML;
                        // Jika tidak ada <span> atau <mark>, berarti morphdom baru saja me-revert
                        if (!html.includes('<span') && !html.includes('<mark')) {
                            this._doHighlight();
                        }
                    });
                    this._observer.observe(this.$refs.codeContent, {
                        childList: true,
                        characterData: true,
                        subtree: true,
                    });
                },

                destroy() {
                    this._observer?.disconnect();
                },

                _doHighlight() {
                    const el = this.$refs.codeContent;
                    if (!el) return;
                    const text = el.textContent;
                    if (!text.trim()) return;
                    this.originalText = text;
                    this.applyHighlight();
                },

                applyHighlight() {
                    this.colorizedHtml = language === 'json' ?
                        this.highlightJson(this.originalText) :
                        this.escapeHtml(this.originalText);
                    this.$refs.codeContent.innerHTML = this.colorizedHtml;
                },

                escapeHtml(str) {
                    return str
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;');
                },

                escapeRegex(str) {
                    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                },

                highlightJson(text) {
                    const bracketColors = ['#ffd700', '#da70d6', '#87ceeb'];
                    const depthStack = [];
                    let depth = 0;
                    let out = '';
                    let i = 0;
                    const len = text.length;

                    const span = (color, content) =>
                        '<span style="color:' + color + '">' + this.escapeHtml(content) + '</span>';

                    while (i < len) {
                        const ch = text[i];

                        // String
                        if (ch === '"') {
                            let j = i + 1;
                            while (j < len) {
                                if (text[j] === '\\') {
                                    j += 2;
                                    continue;
                                }
                                if (text[j] === '"') {
                                    j++;
                                    break;
                                }
                                j++;
                            }
                            const raw = text.slice(i, j);

                            // Peek ahead past whitespace — key if followed by ':'
                            let k = j;
                            while (k < len && /\s/.test(text[k])) k++;
                            const isKey = text[k] === ':';

                            out += isKey ?
                                '<span style="color:#79c0ff">' + this.escapeHtml(raw) + '</span>' :
                                '<span style="color:#a5d6a7">' + this.escapeHtml(raw) + '</span>';
                            i = j;
                            continue;
                        }

                        // Number
                        if (ch === '-' || (ch >= '0' && ch <= '9')) {
                            let j = i + 1;
                            while (j < len && /[\d.eE+\-]/.test(text[j])) j++;
                            out += span('#f0883e', text.slice(i, j));
                            i = j;
                            continue;
                        }

                        // true / false / null
                        if (text.slice(i, i + 4) === 'true') {
                            out += span('#d2a8ff', 'true');
                            i += 4;
                            continue;
                        }
                        if (text.slice(i, i + 5) === 'false') {
                            out += span('#d2a8ff', 'false');
                            i += 5;
                            continue;
                        }
                        if (text.slice(i, i + 4) === 'null') {
                            out += span('#ff7b72', 'null');
                            i += 4;
                            continue;
                        }

                        // Open bracket
                        if (ch === '{' || ch === '[') {
                            const color = bracketColors[depth % bracketColors.length];
                            depthStack.push(color);
                            depth++;
                            out += '<span style="color:' + color + '">' + this.escapeHtml(ch) +
                                '</span>';
                            i++;
                            continue;
                        }

                        // Close bracket
                        if (ch === '}' || ch === ']') {
                            depth = Math.max(0, depth - 1);
                            const color = depthStack.pop() || bracketColors[0];
                            out += '<span style="color:' + color + '">' + this.escapeHtml(ch) +
                                '</span>';
                            i++;
                            continue;
                        }

                        // Colon / Comma
                        if (ch === ':' || ch === ',') {
                            out += span('#8b949e', ch);
                            i++;
                            continue;
                        }

                        // Whitespace & other chars
                        out += this.escapeHtml(ch);
                        i++;
                    }

                    return out;
                },

                performSearch() {
                    const q = this.searchQuery.trim();
                    if (!q) {
                        this.clearHighlight();
                        return;
                    }

                    const htmlQuery = this.escapeHtml(q);
                    const regex = new RegExp(this.escapeRegex(htmlQuery), 'gi');
                    let count = 0;

                    // Split by HTML tags — only match inside text nodes
                    const highlighted = this.colorizedHtml.split(/(<[^>]*>)/).map(part => {
                        if (part.startsWith('<')) return part;
                        return part.replace(regex, m => {
                            count++;
                            return '<mark style="background:rgba(251,191,36,0.25);color:#fde68a;border-radius:2px;padding:0 2px" data-n="' +
                                count + '">' + m + '</mark>';
                        });
                    }).join('');

                    this.matchCount = count;
                    this.currentMatch = count > 0 ? 1 : 0;
                    this.$refs.codeContent.innerHTML = highlighted;
                    this.$nextTick(() => this.scrollToMatch(1));
                },

                clearHighlight() {
                    this.$refs.codeContent.innerHTML = this.colorizedHtml;
                    this.matchCount = 0;
                    this.currentMatch = 0;
                },

                clearSearch() {
                    this.searchQuery = '';
                    this.clearHighlight();
                    this.searchOpen = false;
                },

                scrollToMatch(n) {
                    const marks = this.$refs.codeContent.querySelectorAll('mark');
                    marks.forEach((m, i) => {
                        m.style.outline = i === n - 1 ? '2px solid #f59e0b' : '';
                    });
                    if (marks[n - 1]) marks[n - 1].scrollIntoView({
                        block: 'nearest',
                        behavior: 'smooth'
                    });
                },

                nextMatch() {
                    if (!this.matchCount) return;
                    this.currentMatch = this.currentMatch >= this.matchCount ? 1 : this.currentMatch +
                        1;
                    this.scrollToMatch(this.currentMatch);
                },

                prevMatch() {
                    if (!this.matchCount) return;
                    this.currentMatch = this.currentMatch <= 1 ? this.matchCount : this.currentMatch -
                        1;
                    this.scrollToMatch(this.currentMatch);
                },

                copyToClipboard() {
                    navigator.clipboard.writeText(this.originalText).then(() => {
                        this.copied = true;
                        setTimeout(() => this.copied = false, 2000);
                    });
                },

                onSearchKey(e) {
                    if (e.key === 'Enter') {
                        e.shiftKey ? this.prevMatch() : this.nextMatch();
                    }
                    if (e.key === 'Escape') {
                        this.clearSearch();
                    }
                },
            });
        }
    </script>
@endonce

@once
    @push('styles')
        <style>
            .cb-scroll::-webkit-scrollbar {
                width: 10px;
                height: 10px;
            }

            .cb-scroll::-webkit-scrollbar-track {
                background: #0d1117;
            }

            .cb-scroll::-webkit-scrollbar-thumb {
                background: #3d444d;
                border-radius: 5px;
                border: 2px solid #0d1117;
            }

            .cb-scroll::-webkit-scrollbar-thumb:hover {
                background: #636e7b;
            }

            .cb-scroll::-webkit-scrollbar-corner {
                background: #0d1117;
            }

            .cb-scroll {
                scrollbar-width: thin;
                scrollbar-color: #3d444d #0d1117;
            }
        </style>
    @endpush
@endonce

<div x-data="codeBlock('{{ $language }}')"
    {{ $attributes->merge(['class' => 'w-full flex flex-col rounded-xl overflow-hidden border border-zinc-700/60 bg-[#0d1117] shadow-lg shadow-black/30']) }}>

    {{-- Header --}}
    <div class="flex items-center gap-3 px-4 py-2.5 bg-[#161b22] border-b border-zinc-700/60 min-h-[42px]">

        {{-- Traffic lights --}}
        <div class="flex gap-1.5 shrink-0">
            <div class="w-3 h-3 rounded-full bg-[#ff5f57] shadow-sm shadow-red-900/50"></div>
            <div class="w-3 h-3 rounded-full bg-[#febc2e] shadow-sm shadow-amber-900/50"></div>
            <div class="w-3 h-3 rounded-full bg-[#28c840] shadow-sm shadow-green-900/50"></div>
        </div>

        {{-- Language pill --}}
        @if ($language)
            <span
                class="px-2 py-0.5 rounded-md text-[10px] font-mono font-semibold tracking-widest uppercase
                bg-zinc-700/50 text-zinc-400 border border-zinc-600/40 select-none shrink-0">
                {{ $language }}
            </span>
        @endif

        {{-- Search bar (expanded) --}}
        @if ($searchable)
            <div x-show="searchOpen" x-cloak class="flex flex-1 items-center gap-1.5 min-w-0">
                <div class="relative flex-1">
                    <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3 h-3 text-zinc-500 pointer-events-none"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input x-ref="searchInput" type="text" x-model="searchQuery"
                        @input.debounce.200ms="performSearch" @keydown="onSearchKey($event)"
                        placeholder="Cari dalam kode..."
                        class="w-full bg-[#0d1117] border border-zinc-600/60 text-zinc-200 placeholder-zinc-600
                               text-xs font-mono rounded-md pl-7 pr-24 py-1.5
                               focus:outline-none focus:border-amber-500/60 focus:ring-1 focus:ring-amber-500/30
                               transition-colors" />
                    <span x-show="searchQuery" x-cloak
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] font-mono whitespace-nowrap select-none"
                        :class="matchCount > 0 ? 'text-amber-400' : 'text-zinc-600'"
                        x-text="matchCount > 0 ? currentMatch + ' / ' + matchCount : 'tidak ditemukan'"></span>
                </div>

                <button @click="prevMatch" :disabled="matchCount < 2"
                    class="p-1.5 rounded-md text-zinc-400 hover:text-zinc-100 hover:bg-zinc-700/50
                           disabled:opacity-25 disabled:cursor-not-allowed transition-colors"
                    title="Sebelumnya (Shift+Enter)">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                    </svg>
                </button>
                <button @click="nextMatch" :disabled="matchCount < 2"
                    class="p-1.5 rounded-md text-zinc-400 hover:text-zinc-100 hover:bg-zinc-700/50
                           disabled:opacity-25 disabled:cursor-not-allowed transition-colors"
                    title="Berikutnya (Enter)">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <button @click="clearSearch"
                    class="p-1.5 rounded-md text-zinc-500 hover:text-red-400 hover:bg-red-900/20 transition-colors"
                    title="Tutup (Esc)">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        @endif

        {{-- Actions (right) --}}
        <div class="flex items-center gap-1 ml-auto shrink-0">
            @if ($searchable)
                <button x-show="!searchOpen" @click="searchOpen = true; $nextTick(() => $refs.searchInput?.focus())"
                    class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs text-zinc-400
                           hover:text-zinc-100 hover:bg-zinc-700/50 transition-colors"
                    title="Cari (Ctrl+F)">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <span class="hidden sm:inline">Cari</span>
                </button>
            @endif

            @if ($copyable)
                <button @click="copyToClipboard"
                    class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs transition-colors focus:outline-none"
                    :class="copied
                        ?
                        'text-emerald-400 bg-emerald-900/20' :
                        'text-zinc-400 hover:text-zinc-100 hover:bg-zinc-700/50'">
                    <template x-if="!copied">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                        </svg>
                    </template>
                    <template x-if="copied">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </template>
                    <span class="hidden sm:inline" x-text="copied ? 'Tersalin!' : 'Salin'"></span>
                </button>
            @endif
        </div>
    </div>

    {{-- Code Content --}}
    <div class="relative overflow-auto cb-scroll w-full {{ $maxHeight }}">
        <pre class="p-5 m-0 text-[13px] font-mono text-zinc-300 antialiased leading-6 whitespace-pre-wrap break-words"
            style="tab-size:2"><code x-ref="codeContent" class="block">{{ $slot }}</code></pre>
    </div>
</div>
