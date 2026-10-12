import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/persuratan/data/surat_file_picker.dart';
import 'package:nusa/features/persuratan/domain/persuratan.dart';

String suratError(Object e) => e is ValidationException && e.errors.isNotEmpty
    ? e.errors.values.expand((v) => v).join('\n')
    : e is AppException
    ? e.message
    : 'Data surat belum dapat diproses. Silakan coba lagi.';

class SuratErrorView extends StatelessWidget {
  const SuratErrorView(this.error, {required this.retry, super.key});
  final Object error;
  final VoidCallback retry;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.all(24),
    child: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        const Icon(Icons.cloud_off_outlined, size: 40),
        const SizedBox(height: 12),
        Text(suratError(error), textAlign: TextAlign.center),
        const SizedBox(height: 12),
        OutlinedButton.icon(
          onPressed: retry,
          icon: const Icon(Icons.refresh),
          label: const Text('Coba lagi'),
        ),
      ],
    ),
  );
}

class SuratStatus extends StatelessWidget {
  const SuratStatus(this.label, this.status, {super.key});
  final String label;
  final String status;
  @override
  Widget build(BuildContext context) {
    final color = switch (status) {
      'disahkan' || 'dikirim' || 'selesai' => NusaColors.success,
      'dibatalkan' => NusaColors.danger,
      'dikembalikan' || 'perlu_perbaikan' => const Color(0xff966600),
      _ => NusaColors.primary,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .09),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 12,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

class SuratHeading extends StatelessWidget {
  const SuratHeading(this.title, {super.key});
  final String title;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 20, bottom: 12),
    child: Text(
      title,
      style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
    ),
  );
}

class SuratFacts extends StatelessWidget {
  const SuratFacts(this.facts, {super.key});
  final Map<String, Object?> facts;
  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, c) => Wrap(
      spacing: 16,
      runSpacing: 16,
      children: [
        for (final e in facts.entries)
          SizedBox(
            width: c.maxWidth >= 580
                ? (c.maxWidth - 32) / 3
                : (c.maxWidth - 16) / 2,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  e.key,
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 12,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  suratText(e.value),
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
              ],
            ),
          ),
      ],
    ),
  );
}

class SuratRecipients extends StatefulWidget {
  const SuratRecipients({
    required this.options,
    required this.selected,
    required this.onChanged,
    this.enabled = true,
    super.key,
  });
  final List<SuratData> options;
  final Set<int> selected;
  final ValueChanged<Set<int>> onChanged;
  final bool enabled;
  @override
  State<SuratRecipients> createState() => _SuratRecipientsState();
}

class _SuratRecipientsState extends State<SuratRecipients> {
  late Set<int> _selected = Set.of(widget.selected);
  @override
  void didUpdateWidget(covariant SuratRecipients oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (!identical(oldWidget.selected, widget.selected)) {
      _selected = Set.of(widget.selected);
    }
  }

  void _update(Set<int> value) {
    setState(() => _selected = value);
    widget.onChanged(Set.of(value));
  }

  Future<void> _choose() async {
    final selected = Set<int>.from(_selected);
    var query = '';
    final result = await showModalBottomSheet<Set<int>>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => StatefulBuilder(
        builder: (context, update) => Padding(
          padding: EdgeInsets.only(
            bottom: MediaQuery.viewInsetsOf(context).bottom,
          ),
          child: SizedBox(
            height: MediaQuery.sizeOf(context).height * .75,
            child: Column(
              children: [
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: TextField(
                    decoration: const InputDecoration(
                      labelText: 'Cari guru / pegawai',
                      prefixIcon: Icon(Icons.search),
                    ),
                    onChanged: (v) => update(() => query = v.toLowerCase()),
                  ),
                ),
                Expanded(
                  child: ListView(
                    children: [
                      for (final p in widget.options.where(
                        (p) =>
                            suratText(p['nama_lengkap'])
                                .toLowerCase()
                                .contains(query),
                      ))
                        CheckboxListTile(
                          title: Text(suratText(p['nama_lengkap'])),
                          subtitle: p['jabatan_utama'] == null
                              ? null
                              : Text(suratText(p['jabatan_utama'])),
                          value: selected.contains(suratInt(p['id'])),
                          onChanged: (v) => update(() {
                            if (v == true) {
                              selected.add(suratInt(p['id']));
                            } else {
                              selected.remove(suratInt(p['id']));
                            }
                          }),
                        ),
                    ],
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: FilledButton(
                    onPressed: () => Navigator.pop(context, selected),
                    child: Text('Pilih ${selected.length} penerima'),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
    if (result != null && mounted) _update(result);
  }

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      OutlinedButton.icon(
        key: const Key('surat-recipients'),
        onPressed: widget.enabled ? _choose : null,
        icon: const Icon(Icons.group_add_outlined),
        label: Text('Penerima (${_selected.length})'),
      ),
      Wrap(
        spacing: 6,
        children: [
          for (final id in _selected)
            InputChip(
              label: Text(
                suratText(
                  widget.options
                      .where((p) => suratInt(p['id']) == id)
                      .firstOrNull?['nama_lengkap'],
                  'Pegawai #$id',
                ),
              ),
              onDeleted: widget.enabled
                  ? () => _update(Set<int>.from(_selected)..remove(id))
                  : null,
            ),
        ],
      ),
    ],
  );
}

class SuratUploadField extends ConsumerStatefulWidget {
  const SuratUploadField({
    required this.title,
    required this.onChanged,
    this.primary = false,
    this.maxFiles = 5,
    this.enabled = true,
    super.key,
  });
  final String title;
  final ValueChanged<List<SuratUpload>> onChanged;
  final bool primary;
  final int maxFiles;
  final bool enabled;
  @override
  ConsumerState<SuratUploadField> createState() => _SuratUploadFieldState();
}

class _SuratUploadFieldState extends ConsumerState<SuratUploadField> {
  final _files = <SuratUpload>[];
  bool _picking = false;
  String? _error;
  Future<void> _pick() async {
    if (_picking || !widget.enabled) return;
    final account = ref
        .read(authControllerProvider)
        .value
        ?.session
        ?.pengguna
        .id;
    final source = await showModalBottomSheet<SuratFileSource>(
      context: context,
      useSafeArea: true,
      builder: (context) => Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          ListTile(
            leading: const Icon(Icons.photo_camera_outlined),
            title: const Text('Ambil foto surat'),
            onTap: () => Navigator.pop(context, SuratFileSource.camera),
          ),
          ListTile(
            leading: const Icon(Icons.photo_library_outlined),
            title: const Text('Pilih foto dari galeri'),
            onTap: () => Navigator.pop(context, SuratFileSource.gallery),
          ),
          ListTile(
            leading: const Icon(Icons.attach_file),
            title: const Text('Pilih berkas'),
            onTap: () => Navigator.pop(context, SuratFileSource.file),
          ),
        ],
      ),
    );
    if (source == null || !mounted) return;
    setState(() {
      _picking = true;
      _error = null;
    });
    try {
      final file = await ref
          .read(suratFilePickerProvider)
          .pick(source, primary: widget.primary);
      if (!mounted ||
          account !=
              ref.read(authControllerProvider).value?.session?.pengguna.id ||
          file == null) {
        return;
      }
      validateSuratUpload(file);
      if (file.image) {
        final use = await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            title: const Text('Pratinjau foto surat'),
            content: SizedBox(
              width: 440,
              height: MediaQuery.sizeOf(context).height * .5,
              child: InteractiveViewer(
                child: Image.memory(
                  file.bytes,
                  fit: BoxFit.contain,
                  errorBuilder: (_, _, _) => const Center(
                    child: Text('Foto tidak dapat ditampilkan.'),
                  ),
                ),
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: const Text('Batal'),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(context, true),
                child: const Text('Gunakan foto'),
              ),
            ],
          ),
        );
        if (use != true ||
            !mounted ||
            account !=
                ref.read(authControllerProvider).value?.session?.pengguna.id) {
          return;
        }
      }
      if (widget.maxFiles == 1) _files.clear();
      if (_files.length >= widget.maxFiles) {
        throw ValidationException('Maksimal ${widget.maxFiles} berkas.');
      }
      setState(() => _files.add(file));
      widget.onChanged(List.of(_files));
    } catch (e) {
      if (mounted) setState(() => _error = suratError(e));
    } finally {
      if (mounted) setState(() => _picking = false);
    }
  }

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      SuratHeading(widget.title),
      for (final file in _files)
        ListTile(
          contentPadding: EdgeInsets.zero,
          leading: file.image
              ? SizedBox(
                  width: 48,
                  height: 64,
                  child: Image.memory(
                    file.bytes,
                    fit: BoxFit.contain,
                    errorBuilder: (_, _, _) =>
                        const Icon(Icons.broken_image_outlined),
                  ),
                )
              : const Icon(Icons.description_outlined),
          title: Text(file.name),
          subtitle: Text('${(file.bytes.length / 1024).ceil()} KB'),
          trailing: IconButton(
            tooltip: 'Lepas berkas',
            icon: const Icon(Icons.close),
            onPressed: !widget.enabled || _picking
                ? null
                : () {
                    setState(() => _files.remove(file));
                    widget.onChanged(List.of(_files));
                  },
          ),
        ),
      OutlinedButton.icon(
        key: Key('upload-${widget.title}'),
        onPressed:
            widget.enabled &&
                !_picking &&
                (_files.length < widget.maxFiles || widget.maxFiles == 1)
            ? _pick
            : null,
        icon: const Icon(Icons.add_photo_alternate_outlined),
        label: Text(_picking ? 'Memuat berkas...' : 'Kamera / Galeri / Berkas'),
      ),
      if (_error != null)
        Padding(
          padding: const EdgeInsets.only(top: 8),
          child: Text(
            _error!,
            style: const TextStyle(color: NusaColors.danger),
          ),
        ),
    ],
  );
}
