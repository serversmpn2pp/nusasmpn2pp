import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/shared/widgets/nusa_account_deletion_link.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';
import 'package:nusa/shared/widgets/nusa_logo.dart';
import 'package:nusa/shared/widgets/nusa_privacy_policy_link.dart';
import 'package:nusa/shared/widgets/nusa_school_identity.dart';

class LoginView extends ConsumerStatefulWidget {
  const LoginView({super.key});

  @override
  ConsumerState<LoginView> createState() => _LoginViewState();
}

class _LoginViewState extends ConsumerState<LoginView> {
  final _formKey = GlobalKey<FormState>();
  final _usernameController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _obscurePassword = true;

  @override
  void dispose() {
    _usernameController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    FocusScope.of(context).unfocus();
    await ref
        .read(authControllerProvider.notifier)
        .login(
          username: _usernameController.text,
          password: _passwordController.text,
        );
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authControllerProvider).value;
    final isSubmitting = auth?.isSubmitting ?? false;
    final keyboardVisible = MediaQuery.viewInsetsOf(context).bottom > 0;
    final safe = MediaQuery.paddingOf(context);

    return Scaffold(
      backgroundColor: Colors.white,
      resizeToAvoidBottomInset: true,
      body: Stack(
        children: [
          Positioned.fill(
            child: IgnorePointer(
              child: _LoginPageBackdrop(keyboardVisible: keyboardVisible),
            ),
          ),
          LayoutBuilder(
            builder: (context, constraints) {
              final compact = constraints.maxHeight - safe.vertical < 700;
              final dense = constraints.maxHeight - safe.vertical < 560;
              final gap = dense
                  ? 6.0
                  : compact
                  ? 10.0
                  : 12.0;
              final inputTheme = Theme.of(context).inputDecorationTheme
                  .copyWith(
                    contentPadding: const EdgeInsets.symmetric(
                      horizontal: 16,
                      vertical: 12,
                    ),
                  );

              return CustomScrollView(
                key: const Key('login-scroll'),
                physics: const ClampingScrollPhysics(),
                keyboardDismissBehavior:
                    ScrollViewKeyboardDismissBehavior.onDrag,
                slivers: [
                  SliverPadding(
                    padding: EdgeInsets.fromLTRB(24, safe.top, 24, 0),
                    sliver: SliverFillRemaining(
                      hasScrollBody: false,
                      child: Center(
                        child: ConstrainedBox(
                          constraints: const BoxConstraints(maxWidth: 440),
                          child: AutofillGroup(
                            child: Form(
                              key: _formKey,
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.stretch,
                                children: [
                                  Expanded(
                                    child: _LoginSchoolHeader(
                                      keyboardVisible: keyboardVisible,
                                      compact: compact,
                                      safeTop: safe.top,
                                    ),
                                  ),
                                  Text(
                                    'Selamat Datang',
                                    textAlign: TextAlign.center,
                                    style: Theme.of(context)
                                        .textTheme
                                        .headlineSmall
                                        ?.copyWith(
                                          color: NusaColors.primary,
                                          fontSize: keyboardVisible
                                              ? 22
                                              : compact
                                              ? 26
                                              : 28,
                                          fontWeight: FontWeight.w800,
                                        ),
                                  ),
                                  if (!keyboardVisible && !dense) ...[
                                    const SizedBox(height: 4),
                                    Text(
                                      'Silakan masuk untuk melanjutkan',
                                      textAlign: TextAlign.center,
                                      style: TextStyle(
                                        color: NusaColors.textSecondary,
                                        fontSize: compact ? 13 : 14,
                                      ),
                                    ),
                                  ],
                                  if (auth?.errorMessage
                                      case final message?) ...[
                                    const SizedBox(height: 8),
                                    _ErrorBanner(message: message),
                                  ],
                                  SizedBox(height: gap),
                                  Theme(
                                    data: Theme.of(context).copyWith(
                                      inputDecorationTheme: inputTheme,
                                    ),
                                    child: Column(
                                      children: [
                                        NusaTextField(
                                          fieldKey: const Key('login-username'),
                                          controller: _usernameController,
                                          enabled: !isSubmitting,
                                          hintText: 'NIP / NISN / ORT-NISN',
                                          prefixIcon:
                                              Icons.person_outline_rounded,
                                          autofillHints: const [
                                            AutofillHints.username,
                                          ],
                                          textInputAction: TextInputAction.next,
                                          errorText: auth?.fieldError(
                                            'username',
                                          ),
                                          validator: (value) =>
                                              value == null ||
                                                  value.trim().isEmpty
                                              ? 'NIP, NISN, atau ORT-NISN wajib diisi.'
                                              : null,
                                        ),
                                        SizedBox(height: gap),
                                        NusaTextField(
                                          fieldKey: const Key('login-password'),
                                          controller: _passwordController,
                                          enabled: !isSubmitting,
                                          hintText: 'Kata Sandi',
                                          prefixIcon:
                                              Icons.lock_outline_rounded,
                                          obscureText: _obscurePassword,
                                          autofillHints: const [
                                            AutofillHints.password,
                                          ],
                                          textInputAction: TextInputAction.done,
                                          onFieldSubmitted: (_) => _submit(),
                                          suffixIcon: IconButton(
                                            onPressed: () => setState(
                                              () => _obscurePassword =
                                                  !_obscurePassword,
                                            ),
                                            icon: Icon(
                                              _obscurePassword
                                                  ? Icons.visibility_outlined
                                                  : Icons
                                                        .visibility_off_outlined,
                                            ),
                                            tooltip: _obscurePassword
                                                ? 'Tampilkan kata sandi'
                                                : 'Sembunyikan kata sandi',
                                          ),
                                          validator: (value) =>
                                              value == null || value.isEmpty
                                              ? 'Kata sandi wajib diisi.'
                                              : null,
                                        ),
                                      ],
                                    ),
                                  ),
                                  SizedBox(height: compact ? 12 : 16),
                                  NusaPrimaryButton(
                                    key: const Key('login-submit'),
                                    label: 'Masuk',
                                    loading: isSubmitting,
                                    onPressed: isSubmitting ? null : _submit,
                                  ),
                                  SizedBox(height: compact ? 6 : 8),
                                  const Row(
                                    mainAxisAlignment: MainAxisAlignment.center,
                                    children: [
                                      Icon(
                                        Icons.info_outline_rounded,
                                        size: 15,
                                        color: NusaColors.textSecondary,
                                      ),
                                      SizedBox(width: 6),
                                      Flexible(
                                        child: Text(
                                          'Lupa kata sandi? Silakan hubungi administrator sekolah.',
                                          textAlign: TextAlign.center,
                                          style: TextStyle(
                                            color: NusaColors.textSecondary,
                                            fontSize: 12,
                                            height: 1.3,
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                  _LoginFooter(
                                    compact: compact,
                                    keyboardVisible: keyboardVisible,
                                  ),
                                  SizedBox(height: safe.bottom + 8),
                                ],
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ],
              );
            },
          ),
        ],
      ),
    );
  }
}

class _LoginPageBackdrop extends StatelessWidget {
  const _LoginPageBackdrop({required this.keyboardVisible});

  final bool keyboardVisible;

  @override
  Widget build(BuildContext context) {
    const photo = NusaSchoolPhoto(alignment: Alignment(0, .1));

    return Opacity(
      opacity: keyboardVisible ? .24 : .04,
      child: keyboardVisible
          ? ShaderMask(
              blendMode: BlendMode.dstIn,
              shaderCallback: (bounds) => const LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [
                  Color(0x29FFFFFF),
                  Color(0x33FFFFFF),
                  Colors.white,
                  Color(0x99FFFFFF),
                ],
                stops: [0, .22, .52, 1],
              ).createShader(bounds),
              child: photo,
            )
          : photo,
    );
  }
}

class _LoginSchoolHeader extends StatelessWidget {
  const _LoginSchoolHeader({
    required this.keyboardVisible,
    required this.compact,
    required this.safeTop,
  });

  final bool keyboardVisible;
  final bool compact;
  final double safeTop;

  @override
  Widget build(BuildContext context) => ConstrainedBox(
    key: const Key('login-school-header'),
    constraints: BoxConstraints(minHeight: keyboardVisible ? 76 : 116),
    child: Stack(
      clipBehavior: Clip.none,
      children: [
        if (!keyboardVisible)
          Positioned(
            top: -safeTop,
            left: -24,
            right: -24,
            bottom: -64,
            child: IgnorePointer(
              child: ShaderMask(
                blendMode: BlendMode.dstIn,
                shaderCallback: (bounds) => const LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [
                    Colors.white,
                    Colors.white,
                    Color(0x00FFFFFF),
                    Color(0x00FFFFFF),
                  ],
                  stops: [0, .55, .94, 1],
                ).createShader(bounds),
                child: const _LoginHeaderPhoto(),
              ),
            ),
          ),
        Align(
          alignment: Alignment.topCenter,
          child: Padding(
            padding: EdgeInsets.only(
              top: keyboardVisible
                  ? 0
                  : compact
                  ? 4
                  : 16,
            ),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 360),
              child: Stack(
                children: [
                  Padding(
                    padding: EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: keyboardVisible ? 4 : 10,
                    ),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        NusaBrand(
                          logoSize: keyboardVisible ? 40 : 64,
                          fontSize: keyboardVisible ? 26 : 34,
                        ),
                        const SizedBox(height: 4),
                        Text(
                          'SMP Negeri 2 Padang Panjang',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: NusaColors.primary,
                            fontSize: keyboardVisible
                                ? 12
                                : compact
                                ? 15
                                : 16,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    ),
  );
}

class _LoginHeaderPhoto extends StatelessWidget {
  const _LoginHeaderPhoto();

  static const photo = NusaSchoolPhoto(
    asset: NusaSchoolPhoto.loginAssetPath,
    alignment: Alignment.bottomCenter,
  );

  @override
  Widget build(BuildContext context) => ClipRect(
    child: Stack(
      fit: StackFit.expand,
      children: [
        photo,
        ShaderMask(
          blendMode: BlendMode.dstIn,
          shaderCallback: (bounds) {
            final start = (120 / bounds.height).clamp(0.0, .8);
            final end = (190 / bounds.height).clamp(start, 1.0);
            return LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: const [Colors.white, Colors.white, Color(0x00FFFFFF)],
              stops: [0, start, end],
            ).createShader(bounds);
          },
          child: ImageFiltered(
            imageFilter: ui.ImageFilter.blur(sigmaX: 5, sigmaY: 5),
            child: photo,
          ),
        ),
        const DecoratedBox(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [
                Color(0xA6FFFFFF),
                Color(0x99FFFFFF),
                Color(0x66FFFFFF),
                Color(0x66FFFFFF),
              ],
              stops: [0, .3, .52, 1],
            ),
          ),
        ),
      ],
    ),
  );
}

class _LoginFooter extends StatelessWidget {
  const _LoginFooter({required this.compact, required this.keyboardVisible});

  final bool compact;
  final bool keyboardVisible;

  @override
  Widget build(BuildContext context) {
    if (keyboardVisible) return const SizedBox.shrink();

    const privacy = NusaPrivacyPolicyLink(key: Key('login-privacy-policy'));
    const deletion = NusaAccountDeletionLink(
      key: Key('login-account-deletion'),
    );

    return Padding(
      padding: EdgeInsets.only(top: compact ? 6 : 12),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          NusaTagline(fontSize: compact ? 14 : 16),
          const SizedBox(height: 4),
          TextButtonTheme(
            data: TextButtonThemeData(
              style: TextButton.styleFrom(
                textStyle: Theme.of(context).textTheme.labelLarge
                    ?.copyWith(fontSize: compact ? 12 : 13),
              ),
            ),
            child: compact
                ? const Row(
                    children: [
                      Expanded(child: privacy),
                      Expanded(child: deletion),
                    ],
                  )
                : const Column(children: [privacy, deletion]),
          ),
          const SizedBox(height: 4),
          const Text(
            'Tim Teknisi SMP Negeri 2 Padang Panjang',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 11,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.errorContainer,
      borderRadius: BorderRadius.circular(12),
    ),
    child: Text(
      message,
      style: TextStyle(color: Theme.of(context).colorScheme.onErrorContainer),
    ),
  );
}
