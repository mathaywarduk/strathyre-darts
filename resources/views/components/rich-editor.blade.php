@props(['name', 'value' => '', 'label' => 'Content'])
@php
    // Whatever is put back into the editor (including after a failed save) is cleaned first
    $value = \App\Support\RichText::clean($value) ?? '';
    $id = 'rte-'.$name;
@endphp
<div class="rte" data-rte>
    <label class="label" for="{{ $id }}">{{ $label }}</label>
    <div class="rte-toolbar" role="toolbar" aria-label="Formatting" hidden>
        <button type="button" data-cmd="bold" aria-label="Bold" title="Bold (Ctrl+B)"><b>B</b></button>
        <button type="button" data-cmd="italic" aria-label="Italic" title="Italic (Ctrl+I)"><i>I</i></button>
        <button type="button" data-cmd="underline" aria-label="Underline" title="Underline (Ctrl+U)"><u>U</u></button>
        <button type="button" data-cmd="link" aria-label="Link" title="Link (Ctrl+K)">Link</button>
        <button type="button" data-cmd="insertUnorderedList" aria-label="Bulleted list" title="Bulleted list">• List</button>
        <button type="button" data-cmd="insertOrderedList" aria-label="Numbered list" title="Numbered list">1. List</button>
        <button type="button" data-cmd="insertHorizontalRule" aria-label="Horizontal line" title="Horizontal line">—</button>
    </div>
    <div class="rte-area rich field" contenteditable="true" role="textbox" aria-multiline="true" aria-label="{{ $label }}" hidden></div>
    <textarea class="field rte-source" id="{{ $id }}" name="{{ $name }}" rows="12">{{ $value }}</textarea>
    <p class="hint">Bold, italic, underline, links, lists and lines only. Anything else pasted in is dropped.</p>
</div>
