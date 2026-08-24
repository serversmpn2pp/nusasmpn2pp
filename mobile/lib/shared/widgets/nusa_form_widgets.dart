import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';

class NusaDropdownOption<T> {
  const NusaDropdownOption({
    required this.value,
    required this.label,
    this.enabled = true,
  });

  final T value;
  final String label;
  final bool enabled;
}

class NusaDropdownField<T> extends StatelessWidget {
  const NusaDropdownField({
    required this.fieldKey,
    required this.value,
    required this.options,
    required this.decoration,
    required this.onChanged,
    this.enabled = true,
    this.tooltip,
    this.menuMaxHeight,
    super.key,
  });

  final Key fieldKey;
  final T? value;
  final List<NusaDropdownOption<T>> options;
  final InputDecoration decoration;
  final ValueChanged<T?>? onChanged;
  final bool enabled;
  final String? tooltip;
  final double? menuMaxHeight;

  @override
  Widget build(BuildContext context) {
    final selectedIndex = options.indexWhere((option) => option.value == value);
    final selectedLabel = selectedIndex >= 0
        ? options[selectedIndex].label
        : (decoration.hintText ?? 'Pilih salah satu');
    final effectiveEnabled = enabled && onChanged != null && options.isNotEmpty;
    final effectiveMenuMaxHeight =
        menuMaxHeight ??
        (MediaQuery.sizeOf(context).height * 0.46).clamp(200.0, 360.0);

    return LayoutBuilder(
      builder: (context, constraints) => PopupMenuButton<int>(
        key: fieldKey,
        enabled: effectiveEnabled,
        initialValue: selectedIndex >= 0 ? selectedIndex : null,
        position: PopupMenuPosition.under,
        offset: const Offset(0, 5),
        constraints: BoxConstraints(
          minWidth: constraints.maxWidth,
          maxWidth: constraints.maxWidth,
          maxHeight: effectiveMenuMaxHeight,
        ),
        color: NusaColors.surface,
        elevation: 6,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        tooltip: tooltip ?? decoration.labelText ?? decoration.hintText,
        onSelected: (index) => onChanged?.call(options[index].value),
        itemBuilder: (context) => [
          for (var index = 0; index < options.length; index++)
            PopupMenuItem<int>(
              value: index,
              enabled: options[index].enabled,
              child: Text(
                options[index].label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ),
        ],
        child: InputDecorator(
          isEmpty: false,
          decoration: decoration.copyWith(
            enabled: effectiveEnabled,
            hintText: null,
            suffixIcon:
                decoration.suffixIcon ??
                const Icon(Icons.arrow_drop_down_rounded),
          ),
          child: Text(
            selectedLabel,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ),
      ),
    );
  }
}

class NusaTextField extends StatelessWidget {
  const NusaTextField({
    required this.fieldKey,
    required this.controller,
    required this.hintText,
    required this.prefixIcon,
    this.enabled = true,
    this.obscureText = false,
    this.autofillHints,
    this.textInputAction,
    this.onFieldSubmitted,
    this.onChanged,
    this.validator,
    this.errorText,
    this.suffixIcon,
    super.key,
  });

  final Key fieldKey;
  final TextEditingController controller;
  final String hintText;
  final IconData prefixIcon;
  final bool enabled;
  final bool obscureText;
  final Iterable<String>? autofillHints;
  final TextInputAction? textInputAction;
  final ValueChanged<String>? onFieldSubmitted;
  final ValueChanged<String>? onChanged;
  final FormFieldValidator<String>? validator;
  final String? errorText;
  final Widget? suffixIcon;

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      key: fieldKey,
      controller: controller,
      enabled: enabled,
      obscureText: obscureText,
      autofillHints: autofillHints,
      textInputAction: textInputAction,
      onFieldSubmitted: onFieldSubmitted,
      onChanged: onChanged,
      validator: validator,
      autocorrect: false,
      decoration: InputDecoration(
        hintText: hintText,
        prefixIcon: Icon(prefixIcon),
        suffixIcon: suffixIcon,
        errorText: errorText,
      ),
    );
  }
}

class NusaPrimaryButton extends StatelessWidget {
  const NusaPrimaryButton({
    required this.label,
    required this.onPressed,
    this.loading = false,
    super.key,
  });

  final String label;
  final VoidCallback? onPressed;
  final bool loading;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [NusaColors.primary, NusaColors.primaryDark],
        ),
        borderRadius: BorderRadius.circular(15),
        boxShadow: [
          BoxShadow(
            color: NusaColors.primary.withValues(alpha: 0.18),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Stack(
        children: [
          Positioned(
            right: 0,
            bottom: 0,
            child: Container(
              width: 54,
              height: 5,
              decoration: const BoxDecoration(
                color: NusaColors.accent,
                borderRadius: BorderRadius.only(
                  bottomRight: Radius.circular(15),
                  topLeft: Radius.circular(15),
                ),
              ),
            ),
          ),
          SizedBox(
            width: double.infinity,
            height: 54,
            child: Material(
              color: Colors.transparent,
              child: InkWell(
                onTap: loading ? null : onPressed,
                borderRadius: BorderRadius.circular(15),
                child: Center(
                  child: loading
                      ? const SizedBox.square(
                          dimension: 22,
                          child: CircularProgressIndicator(
                            strokeWidth: 2.4,
                            color: Colors.white,
                          ),
                        )
                      : Text(
                          label,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 16,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
