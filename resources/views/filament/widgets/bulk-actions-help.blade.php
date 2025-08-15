<div class="rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 text-sm">
  <div class="font-semibold mb-2">Bulk actions: when to use which?</div>

  <div class="space-y-2">
    <div>
      <span class="font-medium">“Re-clean selected”</span>
      <ul class="list-disc ps-5 mt-1 space-y-1">
        <li>Re-runs the cleaner on the <em>already stored</em> text.</li>
        <li>No network requests. Fast and safe.</li>
        <li>Use when you changed cleaning rules or want to fix spacing/boilerplate removal.</li>
      </ul>
    </div>

    <div>
      <span class="font-medium">“Refetch & re-clean selected”</span>
      <ul class="list-disc ps-5 mt-1 space-y-1">
        <li>Downloads the page again, then applies the cleaner.</li>
        <li>Updates <code>cleaned_text</code> and may update <code>meta.title</code>.</li>
        <li>Use when the source page changed or extraction logic improved.</li>
        <li>Slower; subject to HTTP guardrails (2xx only, HTML/XHTML content-types, size limits, redirects followed).</li>
      </ul>
    </div>

    <div class="text-gray-600 dark:text-gray-300">
      Tip: After running either action, open a record to copy the cleaned content. The table remains sorted by newest first.
    </div>
  </div>
</div>
