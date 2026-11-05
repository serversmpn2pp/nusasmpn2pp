import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/theme/app_theme.dart';

import '../data/tahfidz_repository.dart';
import '../domain/tahfidz.dart';

typedef TfReload = Future<void> Function();
typedef TfContent = List<Widget> Function(
  BuildContext context,
  WidgetRef ref,
  TfData data,
  TfReload reload,
);

class TfPage extends ConsumerStatefulWidget {
  const TfPage({
    required this.title,
    required this.path,
    required this.content,
    this.query = const {},
    this.pageKey = 'page',
    super.key,
  });
  final String title, path, pageKey;
  final TfData query;
  final TfContent content;
  @override
  ConsumerState<TfPage> createState() => _TfPageState();
}

class _TfPageState extends ConsumerState<TfPage> {
  TfData? _data;
  String? _error;
  bool _loading = true;
  int _page = 1, _request = 0;
  @override
  void initState() {
    super.initState();
    Future.microtask(_load);
  }

  @override
  void didUpdateWidget(TfPage oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.path != widget.path ||
        !mapEquals(oldWidget.query, widget.query)) {
      _page = 1;
      _data = null;
      _load();
    }
  }

  Future<void> _load() async {
    if (!mounted) return;
    final request = ++_request;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await ref.read(tahfidzRepositoryProvider).get(widget.path, {
        ...widget.query,
        widget.pageKey: _page,
      });
      if (mounted && request == _request) setState(() => _data = data);
    } catch (error) {
      if (mounted && request == _request) {
        setState(() {
          _data = null;
          _error = tfError(error);
        });
      }
    } finally {
      if (mounted && request == _request) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final pages = tfMap(_data?['paginasi']);
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.title),
        actions: [
          IconButton(
            tooltip: 'Muat ulang',
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: Align(
          alignment: Alignment.topCenter,
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 900),
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
              children: [
                if (_loading) const LinearProgressIndicator(),
                if (_error != null) ...[
                  TfNotice(_error!),
                  TextButton(onPressed: _load, child: const Text('Coba lagi')),
                ],
                if (_data != null)
                  ...widget.content(context, ref, _data!, _load),
                if (tfInt(pages['terakhir']) > 1)
                  Padding(
                    padding: const EdgeInsets.only(top: 12),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        IconButton(
                          tooltip: 'Halaman sebelumnya',
                          onPressed: !_loading && _page > 1
                              ? () {
                                  _page--;
                                  _load();
                                }
                              : null,
                          icon: const Icon(Icons.chevron_left),
                        ),
                        Text('Halaman $_page / ${pages['terakhir']}'),
                        IconButton(
                          tooltip: 'Halaman berikutnya',
                          onPressed:
                              !_loading && _page < tfInt(pages['terakhir'])
                              ? () {
                                  _page++;
                                  _load();
                                }
                              : null,
                          icon: const Icon(Icons.chevron_right),
                        ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class TfHero extends StatelessWidget {
  const TfHero(this.title, this.subtitle, {this.progress, super.key});
  final String title, subtitle;
  final double? progress;
  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 14),
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [Color(0xFF194C79), Color(0xFF00695C)],
      ),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(Icons.menu_book_rounded, color: Color(0xFFFFCA28), size: 30),
        const SizedBox(height: 10),
        Text(
          title,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 19,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 5),
        Text(
          subtitle,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 12,
            height: 1.4,
          ),
        ),
        if (progress != null)
          Padding(
            padding: const EdgeInsets.only(top: 14),
            child: LinearProgressIndicator(
              value: progress!.clamp(0, 1),
              color: const Color(0xFFFFCA28),
              backgroundColor: Colors.white24,
              borderRadius: BorderRadius.circular(8),
              minHeight: 6,
            ),
          ),
      ],
    ),
  );
}

class TfNotice extends StatelessWidget {
  const TfNotice(this.text, {super.key});
  final String text;
  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    margin: const EdgeInsets.symmetric(vertical: 8),
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Text(text, style: const TextStyle(fontSize: 12, height: 1.45)),
  );
}

class TfCard extends StatelessWidget {
  const TfCard(
    this.title,
    this.subtitle, {
    this.icon = Icons.menu_book_rounded,
    this.trailing,
    this.onTap,
    this.child,
    super.key,
  });
  final String title, subtitle;
  final IconData icon;
  final Widget? trailing, child;
  final VoidCallback? onTap;
  @override
  Widget build(BuildContext context) => Card(
    margin: const EdgeInsets.symmetric(vertical: 5),
    child: InkWell(
      borderRadius: BorderRadius.circular(18),
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: NusaColors.surfaceBlue,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(icon, color: NusaColors.primary, size: 23),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 13,
                        ),
                      ),
                      if (subtitle.isNotEmpty)
                        Padding(
                          padding: const EdgeInsets.only(top: 4),
                          child: Text(
                            subtitle,
                            style: const TextStyle(
                              color: NusaColors.textSecondary,
                              fontSize: 11,
                              height: 1.4,
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
                if (trailing != null)
                  trailing!
                else if (onTap != null)
                  const Icon(
                    Icons.chevron_right_rounded,
                    color: NusaColors.textSecondary,
                  ),
              ],
            ),
            if (child != null)
              Padding(padding: const EdgeInsets.only(top: 12), child: child!),
          ],
        ),
      ),
    ),
  );
}

class TfSection extends StatelessWidget {
  const TfSection(this.text, {super.key});
  final String text;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 18, bottom: 7),
    child: Text(
      text,
      style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800),
    ),
  );
}

class TfAction extends StatefulWidget {
  const TfAction(
    this.label,
    this.run, {
    this.confirm,
    this.icon = Icons.check_rounded,
    this.prominent = false,
    this.subtitle,
    super.key,
  });
  final String label;
  final Future<void> Function() run;
  final String? confirm;
  final IconData icon;
  final bool prominent;
  final String? subtitle;
  @override
  State<TfAction> createState() => _TfActionState();
}

class _TfActionState extends State<TfAction> {
  bool _busy = false;
  Future<void> _run() async {
    setState(() => _busy = true);
    try {
      if (widget.confirm != null) {
        final yes = await showDialog<bool>(
          context: context,
          builder: (ctx) => AlertDialog(
            title: Text(widget.label),
            content: Text(widget.confirm!),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(ctx, false),
                child: const Text('Batal'),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(ctx, true),
                child: const Text('Lanjutkan'),
              ),
            ],
          ),
        );
        if (yes != true || !mounted) return;
      }
      await widget.run();
    } catch (e) {
      if (mounted) tfMessage(context, tfError(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (widget.prominent) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 7),
        child: SizedBox(
          width: double.infinity,
          child: DecoratedBox(
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [NusaColors.primary, NusaColors.primaryDark],
              ),
              borderRadius: BorderRadius.circular(16),
              boxShadow: [
                BoxShadow(
                  color: NusaColors.primary.withValues(alpha: .14),
                  blurRadius: 12,
                  offset: const Offset(0, 5),
                ),
              ],
            ),
            child: Stack(
              children: [
                Positioned(
                  right: 0,
                  bottom: 0,
                  child: Container(
                    width: 55,
                    height: 5,
                    decoration: const BoxDecoration(
                      color: NusaColors.accent,
                      borderRadius: BorderRadius.only(
                        bottomRight: Radius.circular(16),
                        topLeft: Radius.circular(12),
                      ),
                    ),
                  ),
                ),
                Material(
                  color: Colors.transparent,
                  child: Semantics(
                    button: true,
                    enabled: !_busy,
                    child: InkWell(
                      onTap: _busy ? null : _run,
                      borderRadius: BorderRadius.circular(16),
                      child: ConstrainedBox(
                        constraints: const BoxConstraints(minHeight: 68),
                        child: Padding(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 18,
                            vertical: 15,
                          ),
                          child: Row(
                            children: [
                              Icon(
                                _busy
                                    ? Icons.hourglass_top_rounded
                                    : widget.icon,
                                size: 27,
                                color: NusaColors.accent,
                              ),
                              const SizedBox(width: 14),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      widget.label,
                                      style: const TextStyle(
                                        color: Colors.white,
                                        fontSize: 17,
                                        fontWeight: FontWeight.w800,
                                      ),
                                    ),
                                    if (widget.subtitle != null)
                                      Padding(
                                        padding: const EdgeInsets.only(top: 4),
                                        child: Text(
                                          widget.subtitle!,
                                          style: const TextStyle(
                                            color: Colors.white70,
                                            fontSize: 12,
                                            height: 1.35,
                                          ),
                                        ),
                                      ),
                                  ],
                                ),
                              ),
                              const SizedBox(width: 8),
                              const Icon(
                                Icons.chevron_right_rounded,
                                color: Colors.white70,
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      );
    }
    return OutlinedButton.icon(
      style: OutlinedButton.styleFrom(
        minimumSize: const Size(0, 48),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        backgroundColor: Colors.white,
        foregroundColor: NusaColors.primary,
        side: BorderSide(color: NusaColors.primary.withValues(alpha: .35)),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        textStyle: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
      ),
      onPressed: _busy ? null : _run,
      icon: _busy
          ? const Icon(Icons.hourglass_top_rounded, size: 18)
          : Icon(widget.icon, size: 18),
      label: Text(widget.label),
    );
  }
}

void tfMessage(BuildContext context, String text) =>
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
Future<void> tfOpen(BuildContext context, Widget view) async =>
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => view));

Widget tfProgress(List<TfData> rows) => Column(
  children: [
    for (final row in rows)
      TfCard(
        tfText(row['nama']),
        row['nilai'] == null
            ? 'Belum disetor'
            : 'Nilai ${row['nilai']} · ${tfDate(row['tanggal'])}',
        icon: row['nilai'] == null
            ? Icons.radio_button_unchecked_rounded
            : Icons.check_circle_outline_rounded,
      ),
  ],
);
