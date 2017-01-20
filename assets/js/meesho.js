var Meesho = {
    Views: {},
    Models: {},
    Collections: {},
    Router: {}
};

Meesho.Router = Backbone.Router.extend({

    routes: {
        'home': 'home',
        '*path': 'home'
    },

    home: function(){
        var form = new Meesho.Views.Form();
        var products = new Meesho.Views.Products();
        $('#app #productForm').html(form.render().el);
    }
});

$(document).ready(function(){
    Meesho.Router.Instance = new Meesho.Router();
    Backbone.history.start();
});


Meesho.Views.Form = Backbone.View.extend({

    events: {
        "change #uploadBtn":    "handleUpload",
        "click #post":   "handlePost"
    },

    initialize: function(options) {
        var view = this;
        view.uid = Math.random().toString(36).substr(2, 9);
    },

    handleUpload: function(event)
    {

        var form = $('#postForm'),
            data = new FormData(form[0]),
            file = event.target.files[0],
            reader = new FileReader(),
            view = this;
        data.append('uid', view.uid);
        reader.onload = function(){
            blob = reader.result;
        };
        reader.readAsDataURL(file);

        $.ajax({
            url: '/meesho/api/upload',
            type: 'POST',
            processData: false,
            contentType: false,
            data: data,
            success: function(data, textStatus, jqXHR)
            {
                console.log(jqXHR);
            },
            error: function(jqXHR, textStatus, errorThrown)
            {
                console.log('ERRORS: ' + textStatus);
            }
        });

    },

    handlePost: function(event)
    {
        var form = $('#postForm'),
            data = new FormData(form[0]),
            view = this;
        data.append('uid', view.uid);

        $.ajax({
            url: '/meesho/api/post',
            type: 'POST',
            processData: false,
            contentType: false,
            data: data,
            success: function(data, textStatus, jqXHR)
            {
                $('#postForm')[0].reset();
                view.uid = Math.random().toString(36).substr(2, 9);
                //Meesho.Views.Products.collection.reset([{'name': 1}, {'name': 2}]);
            },
            error: function(jqXHR, textStatus, errorThrown)
            {
                console.log('ERRORS: ' + textStatus);
            }
        });

    },

    render: function()
    {
        var res = {};
        this.$el.html(_.template($('script#productUploadForm').html(), {variable: 'data'})(res));
        return this;
    }
});

Meesho.Models.Product = Backbone.Model.extend();

Meesho.Collections.Products = Backbone.Collection.extend({

    model: Meesho.Models.Product,
    url: '/meesho/api/products',

    initialize: function(){
        this.fetch({
            success: this.fetchSuccess,
            error: this.fetchError,
            reset : true
        });
    },

    fetchSuccess: function (collection, response) {
        //console.log('Collection fetch success', response);
        //console.log('Collection models: ', collection.models);
    },

    fetchError: function (collection, response) {
        throw new Error("Products fetch error");
    }

});

Meesho.Views.Products = Backbone.View.extend({

    el: $('#app #products'),

    initialize: function(){
        this.collection = new Meesho.Collections.Products();
        this.listenTo(this.collection, 'reset', this.render);
        this.collection.fetch();
    },

    render: function()
    {
        var self = this;
        this.$el.append('<ul></ul>');
        $ul = this.$el.find('ul');
        _(this.collection.models).each(function(item){
            f = item.toJSON();
            $ul.append('<li><img width="40" height="40" src="'+f.image+'"><p>'+f.name+'</p><p>'+f.price+'</p></li>');
        }, this);
    }
});
