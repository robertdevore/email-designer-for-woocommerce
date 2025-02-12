const { registerBlockType } = wp.blocks;
const { RichText } = wp.blockEditor;

registerBlockType('email-designer-woocommerce/email-template', {
    title: 'Email Template Block',
    icon: 'email',
    category: 'layout',
    attributes: {
        content: {
            type: 'string',
            source: 'html',
            selector: 'p',
        },
    },
    edit({ attributes, setAttributes }) {
        return (
            <RichText
                tagName="p"
                value={attributes.content}
                onChange={(content) => setAttributes({ content })}
                placeholder="Add email content here..."
            />
        );
    },
    save({ attributes }) {
        return <RichText.Content tagName="p" value={attributes.content} />;
    },
});
