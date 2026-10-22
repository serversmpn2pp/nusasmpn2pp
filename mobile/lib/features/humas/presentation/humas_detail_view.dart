import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/humas/data/humas_repository.dart';
import 'package:nusa/features/humas/domain/humas.dart';
import 'package:nusa/features/humas/presentation/humas_agenda_content.dart';
import 'package:nusa/features/humas/presentation/humas_complaint_content.dart';
import 'package:nusa/features/humas/presentation/humas_document_content.dart';
import 'package:nusa/features/humas/presentation/humas_feedback_content.dart';
import 'package:nusa/features/humas/presentation/humas_faq_kinerja_content.dart';
import 'package:nusa/features/humas/presentation/humas_contact_publication_content.dart';
import 'package:nusa/features/humas/presentation/widgets/humas_widgets.dart';
import 'package:nusa/features/humas/presentation/humas_work_views.dart';
import 'package:nusa/features/humas/presentation/humas_catalog_content.dart';

class HumasDetailView extends ConsumerStatefulWidget {
  const HumasDetailView({required this.module, required this.id, super.key});
  final HumasModule module;
  final int id;
  @override
  ConsumerState<HumasDetailView> createState() => _HumasDetailViewState();
}

class _HumasDetailViewState extends ConsumerState<HumasDetailView> {
  HumasData? _data;
  Object? _error;
  bool _loading = true;
  int _request = 0;
  @override
  void initState() {
    super.initState();
    Future.microtask(_load);
  }

  Future<void> _load() async {
    if (!mounted) return;
    final user = ref.read(authControllerProvider).value?.session?.pengguna;
    if (!widget.module.canOpen(user) || widget.id <= 0) {
      setState(() => _loading = false);
      return;
    }
    final request = ++_request;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await ref
          .read(humasRepositoryProvider)
          .get('${widget.module.path}/${widget.id}');
      if (mounted && request == _request) {
        setState(() {
          _data = data;
          _loading = false;
        });
      }
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
  Widget build(BuildContext context) {
    final user = ref.watch(authControllerProvider).value?.session?.pengguna;
    final canOpen = widget.module.canOpen(user) && widget.id > 0;
    final repo = ref.watch(humasRepositoryProvider);
    final manage = widget.module.canManage(user);
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.module.title),
        actions: [
          if (canOpen)
            IconButton(
              tooltip: 'Muat ulang',
              onPressed: _loading ? null : _load,
              icon: const Icon(Icons.refresh),
            ),
        ],
      ),
      body: !canOpen
          ? const Center(
              child: Text('Menu ini tidak tersedia untuk akun Anda.'),
            )
          : SafeArea(
              child: RefreshIndicator(
                onRefresh: _load,
                child: ListView(
                  padding: const EdgeInsets.all(16),
                  physics: const AlwaysScrollableScrollPhysics(),
                  children: [
                    if (_loading)
                      const Center(child: CircularProgressIndicator()),
                    if (_error != null) HumasErrorView(_error!, onRetry: _load),
                    if (!_loading && _error == null && _data != null)
                      switch (widget.module) {
                        HumasModule.assets ||
                        HumasModule.media ||
                        HumasModule.clippings ||
                        HumasModule.achievements ||
                        HumasModule.alumni => HumasCatalogContent(
                          module: widget.module,
                          data: _data!,
                          repository: repo,
                          onChanged: _load,
                        ),
                        HumasModule.programs ||
                        HumasModule.committee ||
                        HumasModule.partners ||
                        HumasModule.accreditation => HumasWorkContent(
                          kind: widget.module.path,
                          base: widget.module.path,
                          data: _data!,
                          repository: repo,
                          onChanged: _load,
                        ),
                        HumasModule.contacts || HumasModule.publications =>
                          HumasContactPublicationContent(
                            module: widget.module,
                            data: _data!,
                            repository: repo,
                            onChanged: _load,
                          ),
                        HumasModule.faq ||
                        HumasModule.performance => HumasFaqKinerjaContent(
                          module: widget.module,
                          data: _data!,
                          repository: repo,
                          manage: manage,
                          onChanged: _load,
                        ),
                        HumasModule.agenda ||
                        HumasModule.invitations => HumasAgendaContent(
                          data: _data!,
                          repository: repo,
                          manage: manage,
                          parent: widget.module.parentOnly,
                          onChanged: _load,
                        ),
                        HumasModule.documents => HumasDocumentContent(
                          data: _data!,
                          repository: repo,
                          manage: manage,
                          onChanged: _load,
                        ),
                        HumasModule.complaints ||
                        HumasModule.myComplaints => HumasComplaintContent(
                          data: _data!,
                          repository: repo,
                          manage: manage,
                          parent: widget.module.parentOnly,
                          userId: user!.id,
                          canHandle:
                              user.administrator ||
                              user.izin.contains('pengaduan_humas.tangani'),
                          onChanged: _load,
                        ),
                        HumasModule.feedback ||
                        HumasModule.myFeedback => HumasFeedbackContent(
                          data: _data!,
                          repository: repo,
                          manage: manage,
                          parent: widget.module.parentOnly,
                          onChanged: _load,
                        ),
                      },
                  ],
                ),
              ),
            ),
    );
  }
}
