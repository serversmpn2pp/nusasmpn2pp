import 'package:flutter/material.dart';
import 'package:nusa/features/humas/data/humas_repository.dart';
import 'package:nusa/features/humas/domain/humas.dart';
import 'package:nusa/features/humas/presentation/widgets/humas_widgets.dart';

/// Detail collections use server pagination, never truncate a meeting's participants.
class HumasCollectionView extends StatefulWidget {
  const HumasCollectionView({
    required this.title,
    required this.path,
    required this.repository,
    required this.itemBuilder,
    this.onAdd,
    super.key,
  });
  final String title;
  final String path;
  final HumasRepository repository;
  final Widget Function(HumasData item, Future<void> Function() reload)
  itemBuilder;
  final Future<void> Function()? onAdd;
  @override
  State<HumasCollectionView> createState() => _HumasCollectionViewState();
}

class _HumasCollectionViewState extends State<HumasCollectionView> {
  final _items = <HumasData>[];
  HumasPage? _page;
  Object? _error;
  bool _loading = true;
  int _request = 0;
  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({bool append = false}) async {
    final request = ++_request;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final page = await widget.repository.page(widget.path, {
        'halaman': append ? (_page?.page ?? 0) + 1 : 1,
        'per_halaman': 30,
      });
      if (!mounted || request != _request) return;
      setState(() {
        if (!append) _items.clear();
        _items.addAll(page.items);
        _page = page;
        _loading = false;
      });
    } catch (error) {
      if (mounted && request == _request) {
        setState(() {
          _error = error;
          _loading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(widget.title),
      actions: [
        IconButton(
          tooltip: 'Muat ulang',
          onPressed: _loading ? null : _load,
          icon: const Icon(Icons.refresh),
        ),
      ],
    ),
    floatingActionButton: widget.onAdd == null
        ? null
        : FloatingActionButton(
            onPressed: () async {
              await widget.onAdd!();
              if (mounted) _load();
            },
            child: const Icon(Icons.add),
          ),
    body: SafeArea(
      child: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            if (_page != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Text('${_page!.total} data'),
              ),
            if (_error != null) HumasErrorView(_error!, onRetry: _load),
            for (final item in _items) widget.itemBuilder(item, _load),
            if (!_loading && _items.isEmpty && _error == null)
              const HumasCard(title: 'Belum ada data'),
            if (_loading) const Center(child: CircularProgressIndicator()),
            if (!_loading && _page?.hasNext == true)
              OutlinedButton(
                onPressed: () => _load(append: true),
                child: const Text('Muat berikutnya'),
              ),
          ],
        ),
      ),
    ),
  );
}
