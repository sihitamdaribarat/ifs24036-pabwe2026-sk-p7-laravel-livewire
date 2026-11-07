<div>
    <!-- Toast UI Editor CDN Styles & Scripts -->
    <link rel="stylesheet" href="https://uicdn.toast.com/editor/latest/toastui-editor.min.css" />
    <script src="https://uicdn.toast.com/editor/latest/toastui-editor-all.min.js"></script>

    @php($statusEnum = $stock->statusEnum())

    <!-- Top Navigation Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div class="flex items-center space-x-3">
            <a href="{{ route('stocks.index') }}" class="p-2 rounded-xl text-slate-500 hover:text-slate-800 hover:bg-slate-200 transition" title="Kembali ke Daftar Stok">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            </a>
            <div>
                <div class="flex items-center space-x-2.5">
                    <h1 class="text-2xl font-bold text-slate-900 truncate max-w-md sm:max-w-xl">{{ $stock->name }}</h1>
                    <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full border {{ $statusEnum->badgeClass() }}">
                        {{ $statusEnum->label() }}
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">
                    <span class="font-mono">SKU: {{ $stock->sku ?? '-' }}</span> &bull;
                    Dibuat pada {{ $stock->created_at ? $stock->created_at->format('d M Y H:i') : '-' }}
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button wire:click="openEditModal" wire:loading.attr="disabled" class="inline-flex items-center space-x-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-75 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                <span>Edit Stok</span>
            </button>

            <button wire:click="confirmDeleteStock" wire:loading.attr="disabled" class="inline-flex items-center space-x-1 px-3 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-semibold rounded-lg transition disabled:opacity-75">
                <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                <span>Hapus</span>
            </button>
        </div>
    </div>

    <!-- Cover Stock Section -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-8">
        <input type="file" wire:model="coverPhoto" id="coverPhotoInput" accept="image/*" class="hidden" aria-label="Upload cover barang">

        @if ($stock->coverImageUrl())
            <div class="relative group h-64 sm:h-80 w-full bg-slate-900 overflow-hidden">
                <img src="{{ $stock->coverImageUrl() }}?v={{ $stock->updated_at?->timestamp }}" alt="Cover {{ $stock->name }}" class="w-full h-full object-cover">

                <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center space-x-3">
                    <button type="button" onclick="document.getElementById('coverPhotoInput').click()" class="px-4 py-2 bg-white text-slate-800 text-xs font-semibold rounded-xl shadow-lg hover:bg-slate-50 transition">
                        Ganti Cover
                    </button>
                    <button type="button" wire:click="confirmDeleteCover" class="px-4 py-2 bg-rose-600 text-white text-xs font-semibold rounded-xl shadow-lg hover:bg-rose-700 transition">
                        Hapus Cover
                    </button>
                </div>
            </div>
        @else
            <div class="p-8 text-center bg-gradient-to-b from-slate-50 to-white flex flex-col items-center justify-center border-b border-slate-100">
                <div class="w-14 h-14 rounded-2xl bg-teal-50 border border-teal-100 flex items-center justify-center text-teal-600 mb-3 shadow-sm">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                </div>
                <h2 class="text-sm font-bold text-slate-800">Tambahkan Foto Cover untuk Barang Ini</h2>
                <p class="text-xs text-slate-500 mt-1 max-w-sm">Mendukung format PNG, JPG, GIF, WEBP hingga 5MB.</p>
                <button type="button" onclick="document.getElementById('coverPhotoInput').click()" class="mt-4 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-xl shadow-sm transition">
                    + Upload Cover Gambar
                </button>
            </div>
        @endif

        <div wire:loading wire:target="coverPhoto" class="p-3 bg-blue-50 border-t border-blue-100 text-center text-xs text-blue-700 font-semibold animate-pulse">
            Sedang mengunggah dan memproses gambar cover...
        </div>
        @error('coverPhoto')
            <div class="p-3 bg-rose-50 border-t border-rose-100 text-center text-xs text-rose-700 font-medium">{{ $message }}</div>
        @enderror
    </div>

    <div class="space-y-6">
        <!-- Metadata + Adjust Quantity -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200 lg:col-span-1">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-2">Sesuaikan Quantity</span>
                <div class="text-3xl font-extrabold text-slate-900">
                    {{ number_format($stock->quantity, 0, ',', '.') }}
                    <span class="text-sm font-semibold text-slate-500">{{ $stock->unit }}</span>
                </div>
                <div class="mt-3 flex items-center gap-2">
                    <button type="button" id="stock-adjust-decrement" wire:click="adjustQuantity(-1)" wire:loading.attr="disabled" @disabled($stock->quantity <= 0)
                        class="w-10 h-10 rounded-lg border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 disabled:opacity-40 disabled:cursor-not-allowed text-lg font-bold transition">&minus;</button>
                    <input wire:model="adjustAmount" type="number" min="1" step="1" aria-label="Jumlah penyesuaian"
                        class="w-20 px-2 py-2 text-center text-sm font-semibold rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <button type="button" id="stock-adjust-increment" wire:click="adjustQuantity(1)" wire:loading.attr="disabled"
                        class="w-10 h-10 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 disabled:opacity-40 text-lg font-bold transition">+</button>
                </div>
                @error('adjustAmount') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                <p class="text-[11px] text-slate-500 mt-2">Status otomatis menyesuaikan: habis jika 0, menipis jika &le; min. stok ({{ $stock->min_stock }}).</p>
            </div>

            <div class="grid grid-cols-2 gap-4 lg:col-span-2">
                <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Status Stok</span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $statusEnum->badgeClass() }}">{{ $statusEnum->label() }}</span>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Harga Satuan</span>
                    <div class="text-sm font-semibold text-slate-800">{{ $stock->price !== null ? 'Rp '.number_format((float) $stock->price, 2, ',', '.') : 'Belum ditentukan' }}</div>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Minimum Stok</span>
                    <div class="text-sm font-semibold text-slate-800">{{ $stock->min_stock }} {{ $stock->unit }}</div>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block mb-1">Nilai Persediaan</span>
                    <div class="text-sm font-semibold text-slate-800">{{ $stock->price !== null ? 'Rp '.number_format((float) $stock->price * $stock->quantity, 0, ',', '.') : '-' }}</div>
                </div>
            </div>
        </div>

        <!-- Description Viewer Section -->
        <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-6">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Deskripsi Barang</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Diformat dengan Toast UI Editor / Markdown</p>
                </div>
                <button wire:click="openEditModal" class="inline-flex items-center space-x-1 px-3 py-1.5 text-xs font-semibold text-blue-600 hover:text-blue-700 hover:bg-blue-50 border border-blue-200 rounded-lg transition">
                    <span>Ubah Deskripsi</span>
                </button>
            </div>

            @if (!empty($stock->description))
                <div wire:ignore wire:key="viewer-{{ $stock->updated_at?->timestamp }}"
                     x-data="{
                         init() {
                             if (window.toastui && toastui.Editor) {
                                 toastui.Editor.factory({
                                     el: this.$refs.viewerContainer,
                                     viewer: true,
                                     initialValue: @js($stock->description)
                                 });
                             }
                         }
                     }"
                     class="prose prose-slate max-w-none text-slate-800">
                    <div x-ref="viewerContainer"></div>
                </div>
            @else
                <div class="py-12 text-center text-slate-500">
                    <p class="text-sm font-medium text-slate-600">Belum ada deskripsi untuk barang ini</p>
                    <p class="text-xs text-slate-500 mt-1">Tambahkan spesifikasi, lokasi rak, atau catatan supplier dengan Toast UI Editor.</p>
                    <button wire:click="openEditModal" class="mt-4 px-3.5 py-1.5 text-xs font-semibold text-blue-600 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition">
                        + Tambahkan Deskripsi Sekarang
                    </button>
                </div>
            @endif
        </div>
    </div>

    <!-- Edit Stock Modal with Toast UI Editor -->
    @if ($showEditModal)
        <div x-data="{ show: true }" x-show="show" x-cloak
            class="modal-scroll-lock fixed inset-0 z-50 flex items-center justify-center overflow-y-auto overscroll-contain p-4 bg-slate-900/50 backdrop-blur-sm">
            <div class="bg-white rounded-2xl max-w-2xl w-full shadow-2xl border border-slate-200 max-h-[90vh] overflow-hidden flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 bg-blue-600">
                    <div>
                        <h2 class="text-lg font-bold text-white">Ubah Data Stok</h2>
                        <p class="text-xs text-white">Edit nama, SKU, quantity, harga, min. stok, status, dan deskripsi</p>
                    </div>
                    <button type="button" @click="show = false; $wire.closeEditModal()" class="text-white/80 hover:text-white transition" aria-label="Tutup">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form wire:submit.prevent="saveStock" class="flex flex-col flex-1 min-h-0">
                    <div class="space-y-5 px-6 py-5 overflow-y-auto flex-1">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="sm:col-span-2">
                                <label for="stock-edit-name" class="block text-sm font-semibold text-slate-800 mb-1">Nama Barang</label>
                                <input wire:model="name" id="stock-edit-name" type="text" class="w-full px-4 py-2 text-sm font-medium rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                @error('name') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label for="stock-edit-sku" class="block text-sm font-semibold text-slate-800 mb-1">SKU</label>
                                <input wire:model="sku" id="stock-edit-sku" type="text" class="w-full px-4 py-2 text-sm font-mono rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                @error('sku') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <div>
                                <label for="stock-edit-quantity" class="block text-sm font-semibold text-slate-800 mb-1">Quantity</label>
                                <input wire:model="quantity" id="stock-edit-quantity" type="number" min="0" step="1" class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                @error('quantity') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label for="stock-edit-unit" class="block text-sm font-semibold text-slate-800 mb-1">Satuan</label>
                                <input wire:model="unit" id="stock-edit-unit" type="text" class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                @error('unit') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label for="stock-edit-price" class="block text-sm font-semibold text-slate-800 mb-1">Harga (Rp)</label>
                                <input wire:model="price" id="stock-edit-price" type="number" min="0" step="0.01" class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                @error('price') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label for="stock-edit-min-stock" class="block text-sm font-semibold text-slate-800 mb-1">Min. Stok</label>
                                <input wire:model="minStock" id="stock-edit-min-stock" type="number" min="0" step="1" class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                @error('minStock') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label for="stock-edit-status" class="block text-sm font-semibold text-slate-800 mb-1">Status</label>
                            <select wire:model="status" id="stock-edit-status" class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                                <option value="">Otomatis (berdasarkan quantity vs min. stok)</option>
                                @foreach ($statuses as $st)
                                    <option value="{{ $st->value }}">{{ $st->label() }}</option>
                                @endforeach
                            </select>
                            @error('status') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-sm font-semibold text-slate-800">Deskripsi Barang (Toast UI Editor)</label>
                                <span class="text-xs text-slate-500">Markdown & WYSIWYG</span>
                            </div>

                            <div wire:ignore
                                 x-data="{
                                     desc: @entangle('description'),
                                     editor: null,
                                     init() {
                                         this.editor = new toastui.Editor({
                                             el: this.$refs.detailEditorContainer,
                                             height: '320px',
                                             initialEditType: 'wysiwyg',
                                             previewStyle: 'vertical',
                                             initialValue: this.desc || '',
                                             placeholder: 'Tuliskan spesifikasi barang dalam format teks atau markdown...',
                                             events: {
                                                 change: () => {
                                                     this.desc = this.editor.getMarkdown();
                                                 }
                                             }
                                         });

                                         this.$watch('desc', value => {
                                             if (this.editor && value !== this.editor.getMarkdown()) {
                                                 this.editor.setMarkdown(value || '', false);
                                             }
                                         });
                                     }
                                 }"
                                 class="rounded-xl border border-slate-300 overflow-hidden bg-white">
                                <div x-ref="detailEditorContainer"></div>
                            </div>
                            @error('description') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 px-6 py-4 bg-blue-50 border-t border-blue-100">
                        <button type="button" @click="show = false; $wire.closeEditModal()" class="px-4 py-2 text-sm font-semibold text-slate-600 bg-white hover:bg-slate-100 border border-slate-200 rounded-xl transition">
                            Batal
                        </button>
                        <button type="submit" wire:loading.attr="disabled" class="px-6 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-75 disabled:cursor-not-allowed rounded-xl shadow-sm transition">
                            <span wire:loading.remove wire:target="saveStock">Simpan Perubahan</span>
                            <span wire:loading wire:target="saveStock">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Modal Konfirmasi Hapus Stok -->
    @if ($confirmingStockDeletion)
        <div x-data="{ show: true }" x-show="show" x-cloak
            class="modal-scroll-lock fixed inset-0 z-50 flex items-center justify-center overflow-y-auto overscroll-contain p-4 bg-slate-900/50 backdrop-blur-sm">
            <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 bg-rose-600">
                    <h2 class="text-base font-bold text-white">Konfirmasi Hapus Stok</h2>
                    <p class="text-xs text-white">Tindakan ini tidak dapat dibatalkan</p>
                </div>
                <div class="px-6 py-5">
                    <p class="text-sm text-slate-600">Apakah Anda yakin ingin menghapus item stok ini secara permanen?</p>
                </div>
                <div class="flex items-center justify-end gap-3 px-6 py-4 bg-rose-50 border-t border-rose-100">
                    <button type="button" @click="show = false; $wire.cancelDeleteStock()" class="px-4 py-2 text-xs font-semibold text-slate-600 bg-white hover:bg-slate-100 border border-slate-200 rounded-lg transition">
                        Batal
                    </button>
                    <button type="button" wire:click="deleteStock" wire:loading.attr="disabled" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 disabled:opacity-75 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                        <span wire:loading.remove wire:target="deleteStock">Ya, Hapus Stok</span>
                        <span wire:loading wire:target="deleteStock">Menghapus...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Konfirmasi Hapus Cover -->
    @if ($confirmingCoverDeletion)
        <div x-data="{ show: true }" x-show="show" x-cloak
            class="modal-scroll-lock fixed inset-0 z-50 flex items-center justify-center overflow-y-auto overscroll-contain p-4 bg-slate-900/50 backdrop-blur-sm">
            <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 bg-rose-600">
                    <h2 class="text-base font-bold text-white">Konfirmasi Hapus Cover</h2>
                    <p class="text-xs text-white">Gambar cover akan dihapus dari item stok ini</p>
                </div>
                <div class="px-6 py-5">
                    <p class="text-sm text-slate-600">Apakah Anda yakin ingin menghapus gambar cover barang ini?</p>
                </div>
                <div class="flex items-center justify-end gap-3 px-6 py-4 bg-rose-50 border-t border-rose-100">
                    <button type="button" @click="show = false; $wire.cancelDeleteCover()" class="px-4 py-2 text-xs font-semibold text-slate-600 bg-white hover:bg-slate-100 border border-slate-200 rounded-lg transition">
                        Batal
                    </button>
                    <button type="button" wire:click="deleteCoverPhoto" wire:loading.attr="disabled" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 disabled:opacity-75 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                        <span wire:loading.remove wire:target="deleteCoverPhoto">Ya, Hapus Cover</span>
                        <span wire:loading wire:target="deleteCoverPhoto">Menghapus...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
