@foreach($classesByMajor as $majorCode => $majorClasses)
    <optgroup label="{{ $majorCode }} — {{ $classMajorLabels[$majorCode] ?? 'Jurusan lainnya' }}">
        @foreach($majorClasses as $class)
            <option value="{{ $class->id }}" @selected((string) $selectedClassId === (string) $class->id)>{{ $class->class_name }}</option>
        @endforeach
    </optgroup>
@endforeach
