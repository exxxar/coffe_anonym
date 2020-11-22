<div class="well col-sm-8">
    <p><strong>Имя </strong> {{ $feedback["name"] }}</p>
    <p><strong>Телефон </strong>{{ $feedback["phone"] }}</p>
    <p><strong>Дата заявки </strong>{{ $feedback["date"] }}</p>
    @isset($feedback["message"])
        <p><strong>Сообщение </strong>{{ $feedback["message"] }}</p>
    @endisset
</div>
